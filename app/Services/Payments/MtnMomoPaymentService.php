<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * MTN Mobile Money Collection API "Request to Pay" integration (TOR §5.2
 * Payment Service, §6.5 Payments, §10 Integrations).
 *
 * Live flow: we POST a requesttopay with a fresh UUID (X-Reference-Id, the
 * idempotency key), MTN pushes a PIN prompt to the customer's phone, and the
 * final status arrives via callback and/or by polling GET requesttopay/{id}.
 * The callback payload is never trusted by itself: in live mode we re-query
 * MTN for the authoritative status.
 *
 * In "simulate" mode (config('momo.mode'), default) no external call is
 * made: the customer is sent to a local confirmation page
 * (routes/web.php `checkout.sandbox-pay`) so the full order -> pay ->
 * confirm -> fulfil flow runs with zero credentials.
 */
class MtnMomoPaymentService implements PaymentGatewayInterface
{
    public function __construct(protected OrderService $orders) {}

    public function initiateCheckout(Order $order, ?string $payerPhone = null): array
    {
        $msisdn = static::normalizeMsisdn($payerPhone ?: $order->delivery_phone);

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'mtn_momo',
            'provider_reference' => (string) Str::uuid(),
            'payer_phone' => $msisdn,
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
        ]);

        if ($this->isSimulated()) {
            return [
                'payment' => $payment,
                'redirect_url' => URL::route('checkout.sandbox-pay', $payment->reference),
            ];
        }

        $body = [
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'currency' => config('momo.currency'),
            'externalId' => $payment->reference,
            'payer' => ['partyIdType' => 'MSISDN', 'partyId' => $msisdn],
            'payerMessage' => 'CY-Market order '.$order->order_number,
            'payeeNote' => 'Order '.$order->order_number,
        ];

        try {
            $headers = ['X-Reference-Id' => $payment->provider_reference];
            if (filled(config('momo.callback_url'))) {
                $headers['X-Callback-Url'] = config('momo.callback_url');
            }

            $response = $this->client()->withHeaders($headers)
                ->post('/collection/v1_0/requesttopay', $body);

            $payment->update(['raw_request' => $body, 'raw_response' => ['http' => $response->status(), 'body' => $response->json()]]);

            if ($response->status() !== 202) {
                Log::channel('momo')->error('MTN MoMo request-to-pay rejected', ['status' => $response->status(), 'body' => $response->body()]);
                $payment->update(['status' => PaymentStatus::Failed]);

                throw new RuntimeException('Unable to start the MTN MoMo payment right now. Please check the number and try again.');
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('momo')->error('MTN MoMo request-to-pay exception', ['error' => $e->getMessage()]);
            $payment->update(['status' => PaymentStatus::Failed]);

            throw new RuntimeException('Unable to reach MTN MoMo right now. Please try again.');
        }

        return [
            'payment' => $payment,
            'redirect_url' => URL::route('checkout.return', $payment->reference),
        ];
    }

    /**
     * MTN callback body: {externalId, referenceId?, status, financialTransactionId?, reason?, ...}.
     * Simulate mode also uses this with a payload built by the sandbox page.
     */
    public function handleCallback(array $payload): Payment
    {
        $payment = Payment::query()
            ->when(isset($payload['externalId']), fn ($q) => $q->where('reference', $payload['externalId']))
            ->when(! isset($payload['externalId']) && isset($payload['referenceId']), fn ($q) => $q->where('provider_reference', $payload['referenceId']))
            ->firstOrFail();

        if ($this->isTerminal($payment)) {
            return $payment;
        }

        if (! $this->isSimulated()) {
            // Callbacks are unauthenticated: confirm with MTN directly.
            return $this->refreshStatus($payment);
        }

        return $this->applyStatus($payment, $payload);
    }

    public function refreshStatus(Payment $payment): Payment
    {
        if ($this->isTerminal($payment) || $this->isSimulated()) {
            return $payment;
        }

        try {
            $response = $this->client()->get('/collection/v1_0/requesttopay/'.$payment->provider_reference);
        } catch (\Throwable $e) {
            Log::channel('momo')->warning('MTN MoMo status check failed', ['reference' => $payment->reference, 'error' => $e->getMessage()]);

            return $payment;
        }

        if (! $response->successful()) {
            Log::channel('momo')->warning('MTN MoMo status check non-2xx', ['reference' => $payment->reference, 'status' => $response->status()]);

            return $payment;
        }

        return $this->applyStatus($payment, $response->json() ?? []);
    }

    /**
     * Resolve a pending live payment; if MTN still reports it pending after
     * the expiry window, mark it failed so the stock reservation is released
     * and the customer can retry.
     */
    public function expireStale(Payment $payment): Payment
    {
        $payment = $this->refreshStatus($payment);

        if ($payment->status === PaymentStatus::Pending
            && $payment->created_at->lt(now()->subMinutes((int) config('momo.pending_expiry_minutes', 10)))) {
            $payment->update(['status' => PaymentStatus::Failed]);
            $this->orders->markPaymentFailed($payment->fresh());
        }

        return $payment->fresh();
    }

    protected function applyStatus(Payment $payment, array $data): Payment
    {
        $status = strtoupper((string) ($data['status'] ?? $data['Status'] ?? ''));

        if ($status === 'PENDING' || $status === '') {
            return $payment;
        }

        $successful = in_array($status, ['SUCCESSFUL', 'SUCCESS'], true);

        $payment->update([
            'provider_transaction_id' => $data['financialTransactionId'] ?? $data['transactionId'] ?? null,
            'channel' => 'mtn-momo',
            'status' => $successful ? PaymentStatus::Successful : PaymentStatus::Failed,
            'raw_response' => $data,
            'paid_at' => $successful ? now() : null,
        ]);

        if ($successful) {
            $this->orders->markPaid($payment->fresh());
        } else {
            $this->orders->markPaymentFailed($payment->fresh());
        }

        return $payment->fresh();
    }

    protected function isTerminal(Payment $payment): bool
    {
        return in_array($payment->status, [PaymentStatus::Successful, PaymentStatus::Refunded, PaymentStatus::Failed, PaymentStatus::Cancelled], true);
    }

    protected function isSimulated(): bool
    {
        return config('momo.mode', 'simulate') !== 'live';
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('momo.base_url'), '/'))
            ->timeout((int) config('momo.timeout', 30))
            ->acceptJson()
            ->withToken($this->accessToken())
            ->withHeaders([
                'X-Target-Environment' => config('momo.environment'),
                'Ocp-Apim-Subscription-Key' => config('momo.subscription_key'),
            ]);
    }

    protected function accessToken(): string
    {
        return Cache::remember('momo.collection.token', 50 * 60, function () {
            $response = Http::baseUrl(rtrim((string) config('momo.base_url'), '/'))
                ->timeout((int) config('momo.timeout', 30))
                ->withBasicAuth((string) config('momo.api_user'), (string) config('momo.api_key'))
                ->withHeaders(['Ocp-Apim-Subscription-Key' => config('momo.subscription_key')])
                ->post('/collection/token/');

            if (! $response->successful() || blank($response->json('access_token'))) {
                Log::channel('momo')->error('MTN MoMo token request failed', ['status' => $response->status(), 'body' => $response->body()]);

                throw new RuntimeException('Unable to authenticate with MTN MoMo.');
            }

            return $response->json('access_token');
        });
    }

    /**
     * Normalise a Ghana number ("024 123 4567", "+233241234567") to the
     * international MSISDN MTN expects: 233241234567.
     */
    public static function normalizeMsisdn(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '233')) {
            return $digits;
        }

        return '233'.ltrim($digits, '0');
    }
}

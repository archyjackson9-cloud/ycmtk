<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Hubtel Mobile Money "Receive Money" / Checkout integration (TOR §5.2
 * Payment Service, §6.5 Payments, §10 Integrations).
 *
 * In "sandbox" mode (config('hubtel.mode'), default) no external call is
 * made: a Payment row is created and the customer is sent to a local
 * sandbox confirmation page (routes/web.php `checkout.sandbox-pay`) so the
 * complete order -> pay -> webhook -> fulfil flow can be exercised and
 * demoed with zero Hubtel credentials. Switching HUBTEL_MODE=live in .env
 * (once credentials are supplied) routes through the real API below
 * without touching any other application code.
 */
class HubtelPaymentService implements PaymentGatewayInterface
{
    public function __construct(protected OrderService $orders) {}

    public function initiateCheckout(Order $order): array
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'hubtel',
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
        ]);

        if ($this->isSandbox()) {
            return [
                'payment' => $payment,
                'redirect_url' => URL::route('checkout.sandbox-pay', $payment->reference),
            ];
        }

        try {
            $response = Http::timeout((int) config('hubtel.timeout', 30))
                ->withBasicAuth(config('hubtel.client_id'), config('hubtel.client_secret'))
                ->post(rtrim((string) config('hubtel.base_url'), '/').'/items/initiate', [
                    'totalAmount' => (float) $order->total,
                    'description' => 'CY-Market order '.$order->order_number,
                    'callbackUrl' => config('hubtel.callback_url'),
                    'returnUrl' => URL::route('checkout.return', $payment->reference),
                    'merchantAccountNumber' => config('hubtel.merchant_account_number'),
                    'cancellationUrl' => URL::route('checkout.return', $payment->reference),
                    'clientReference' => $payment->reference,
                ]);

            $payment->update(['raw_request' => $response->effectiveUri() ? ['url' => (string) $response->effectiveUri()] : null, 'raw_response' => $response->json()]);

            if (! $response->successful() || blank($response->json('data.checkoutUrl'))) {
                Log::channel('hubtel')->error('Hubtel checkout initiation failed', ['response' => $response->body()]);
                $payment->update(['status' => PaymentStatus::Failed]);

                throw new RuntimeException('Unable to reach Hubtel right now. Please try again.');
            }

            return [
                'payment' => $payment,
                'redirect_url' => $response->json('data.checkoutUrl'),
            ];
        } catch (\Throwable $e) {
            Log::channel('hubtel')->error('Hubtel checkout initiation exception', ['error' => $e->getMessage()]);
            $payment->update(['status' => PaymentStatus::Failed]);

            throw $e;
        }
    }

    /**
     * Handle Hubtel's webhook payload (TOR §5.2 "webhook handling,
     * idempotency"). Expected shape (mirrors Hubtel's Receive Money
     * callback):
     *   {
     *     "ClientReference": "PAY-...",
     *     "TransactionId": "...",
     *     "Status": "Success" | "Failed",
     *     "Amount": 123.45,
     *     "Channel": "mtn-gh"
     *   }
     */
    public function handleCallback(array $payload): Payment
    {
        $reference = $payload['ClientReference'] ?? $payload['clientReference'] ?? null;

        $payment = Payment::where('reference', $reference)->firstOrFail();

        // Idempotency guard: a webhook that arrives twice (or a
        // reconciliation job replaying it) must not double-process a
        // payment that has already reached a terminal state.
        if (in_array($payment->status, [PaymentStatus::Successful, PaymentStatus::Refunded], true)) {
            return $payment;
        }

        $status = strtolower((string) ($payload['Status'] ?? $payload['status'] ?? ''));
        $successful = in_array($status, ['success', 'successful', 'paid'], true);

        $payment->update([
            'hubtel_transaction_id' => $payload['TransactionId'] ?? $payload['transactionId'] ?? null,
            'channel' => $payload['Channel'] ?? $payload['channel'] ?? null,
            'status' => $successful ? PaymentStatus::Successful : PaymentStatus::Failed,
            'raw_response' => $payload,
            'paid_at' => $successful ? now() : null,
        ]);

        if ($successful) {
            $this->orders->markPaid($payment->fresh());
        } else {
            $this->orders->markPaymentFailed($payment->fresh());
        }

        return $payment->fresh();
    }

    protected function isSandbox(): bool
    {
        return config('hubtel.mode', 'sandbox') !== 'live';
    }
}

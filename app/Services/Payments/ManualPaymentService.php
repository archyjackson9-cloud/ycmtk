<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Support\Facades\URL;

/**
 * "Pay by transfer" - the pilot's initial payment method. The customer pays
 * an account number published on the instructions page outside the system
 * entirely (no API involved), so there is nothing to call and nothing that
 * confirms itself: a staff member has to see the money land and confirm the
 * matching Payment by hand from Admin > Payments (see PaymentResource).
 *
 * Because that confirmation can take hours rather than seconds, the order's
 * stock reservation window for this gateway is set separately and much
 * longer than MTN MoMo's - see SettingsService::manualPaymentReservationHours()
 * and CheckoutService::checkout().
 */
class ManualPaymentService implements PaymentGatewayInterface
{
    public function __construct(protected OrderService $orders) {}

    public function initiateCheckout(Order $order, ?string $payerPhone = null): array
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'manual',
            'payer_phone' => $payerPhone ?: $order->delivery_phone,
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
        ]);

        return [
            'payment' => $payment,
            'redirect_url' => URL::route('checkout.manual-pay', $payment->reference),
        ];
    }

    /**
     * Applies a staff confirmation/rejection. `handleCallback()` exists to
     * satisfy the shared gateway interface (and to keep this testable the
     * same way as the other gateways); the Filament action calls the
     * `confirm()` wrapper below rather than building this payload itself.
     *
     * Payload: {reference, status: SUCCESSFUL|FAILED, confirmed_by?: int,
     * amount_received?: float, note?: string}.
     */
    public function handleCallback(array $payload): Payment
    {
        $payment = Payment::where('reference', $payload['reference'])->firstOrFail();

        if ($this->isTerminal($payment)) {
            return $payment;
        }

        $successful = strtoupper((string) ($payload['status'] ?? '')) === 'SUCCESSFUL';

        $payment->update([
            'status' => $successful ? PaymentStatus::Successful : PaymentStatus::Failed,
            'confirmed_by' => $payload['confirmed_by'] ?? null,
            'note' => $payload['note'] ?? null,
            'raw_response' => array_filter([
                'confirmed_at' => now()->toIso8601String(),
                'amount_received' => $payload['amount_received'] ?? null,
            ]),
            'paid_at' => $successful ? now() : null,
        ]);

        if ($successful) {
            $this->orders->markPaid($payment->fresh());
        } else {
            $this->orders->markPaymentFailed($payment->fresh());
        }

        return $payment->fresh();
    }

    /**
     * Staff-facing entry point used by PaymentResource's Confirm/Reject
     * actions (Admin > Payments).
     */
    public function confirm(Payment $payment, User $admin, bool $approved, ?float $amountReceived = null, ?string $note = null): Payment
    {
        return $this->handleCallback([
            'reference' => $payment->reference,
            'status' => $approved ? 'SUCCESSFUL' : 'FAILED',
            'confirmed_by' => $admin->id,
            'amount_received' => $amountReceived,
            'note' => $note,
        ]);
    }

    /**
     * There is no external system to poll - a manual payment only changes
     * state when a staff member confirms it via confirm() above.
     */
    public function refreshStatus(Payment $payment): Payment
    {
        return $payment;
    }

    protected function isTerminal(Payment $payment): bool
    {
        return in_array($payment->status, [PaymentStatus::Successful, PaymentStatus::Refunded, PaymentStatus::Failed, PaymentStatus::Cancelled], true);
    }
}

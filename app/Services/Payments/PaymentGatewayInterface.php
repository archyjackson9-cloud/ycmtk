<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Create a Payment record and start the payment attempt. Returns the
     * Payment plus a URL the customer's browser should be sent to (the
     * order page while they approve the prompt on their phone, or a local
     * sandbox confirmation page in simulate mode).
     *
     * @return array{payment: Payment, redirect_url: string}
     */
    public function initiateCheckout(Order $order, ?string $payerPhone = null): array;

    /**
     * Process a status callback from the gateway. Must be idempotent -
     * calling it twice for the same transaction has no additional effect
     * (TOR §11 idempotency keys).
     */
    public function handleCallback(array $payload): Payment;

    /**
     * Ask the gateway for the current status of a pending payment and apply
     * it. Used by the customer-facing status poll and the reconciliation job.
     */
    public function refreshStatus(Payment $payment): Payment;
}

<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Create a Payment record and start the payment attempt. Returns the
     * Payment plus a URL the customer's browser should be sent to in order
     * to approve payment (a hosted Hubtel checkout page in live mode, or a
     * local sandbox confirmation page in sandbox mode).
     *
     * @return array{payment: Payment, redirect_url: string}
     */
    public function initiateCheckout(Order $order): array;

    /**
     * Process a webhook/callback payload from the gateway. Must be
     * idempotent - calling it twice for the same transaction has no
     * additional effect (TOR §11 idempotency keys).
     */
    public function handleCallback(array $payload): Payment;
}

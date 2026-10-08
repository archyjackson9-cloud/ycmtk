<?php

namespace App\Services\Payments;

use InvalidArgumentException;

/**
 * CY-Market supports more than one payment method at once now (MTN MoMo,
 * pay-by-transfer), so - unlike the single `PaymentGatewayInterface`
 * binding in AppServiceProvider, which still exists for callers that only
 * ever deal with one gateway (the MTN webhook, the MoMo simulate route) -
 * CheckoutService and anything that needs "whichever gateway this specific
 * payment/checkout selection uses" goes through here instead.
 */
class PaymentGatewayResolver
{
    public function __construct(
        protected MtnMomoPaymentService $momo,
        protected ManualPaymentService $manual,
    ) {}

    /**
     * @param  string  $method  The checkout form's `payment_method` value.
     */
    public function forMethod(string $method): PaymentGatewayInterface
    {
        return match ($method) {
            'manual' => $this->manual,
            'momo' => $this->momo,
            default => throw new InvalidArgumentException("Unknown payment method [{$method}]."),
        };
    }

    /**
     * @param  string  $provider  A Payment row's `provider` column.
     */
    public function forProvider(string $provider): PaymentGatewayInterface
    {
        return match ($provider) {
            'manual' => $this->manual,
            default => $this->momo, // mtn_momo today; new gateways add cases here.
        };
    }
}

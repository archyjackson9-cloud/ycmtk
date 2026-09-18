<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when cart/checkout re-validation fails (TOR §11 "Product becomes
 * unavailable while in cart -> At checkout, system re-validates stock and
 * minimum quantities; removes or adjusts unavailable items with clear
 * notification"). $issues is a list of human-readable messages the
 * controller surfaces back to the customer.
 */
class CheckoutException extends Exception
{
    /** @param array<int, string> $issues */
    public function __construct(protected array $issues, string $message = 'Your cart needs attention before checkout can continue.')
    {
        parent::__construct($message);
    }

    public function issues(): array
    {
        return $this->issues;
    }
}

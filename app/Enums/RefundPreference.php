<?php

namespace App\Enums;

/**
 * Customer preference when payment succeeded but the order cannot be
 * fulfilled (TOR §6.5 Payments, §11 Fallbacks).
 */
enum RefundPreference: string
{
    case Refund = 'refund';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Refund => 'Full Refund',
            self::Credit => 'Credit for Next-Day Delivery',
        };
    }
}

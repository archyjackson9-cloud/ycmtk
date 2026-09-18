<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Successful => 'Successful',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Successful => 'success',
            self::Failed => 'danger',
            self::Refunded => 'gray',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Literal Tailwind classes for storefront badges - see
     * OrderStatus::badgeClasses() for why these are written out in full.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-700',
            self::Successful => 'bg-emerald-100 text-emerald-700',
            self::Failed => 'bg-red-100 text-red-700',
            self::Refunded => 'bg-gray-100 text-gray-700',
            self::Cancelled => 'bg-gray-100 text-gray-700',
        };
    }
}

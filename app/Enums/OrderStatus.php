<?php

namespace App\Enums;

/**
 * Order lifecycle status (TOR §6.4 Order Lifecycle Management).
 *
 * Pending Payment -> Paid/Confirmed -> Processing -> Dispatched -> Delivered -> Completed
 * with a Cancelled branch available at various stages, and an On Hold state
 * for the payment-succeeded-but-stock-insufficient race condition (TOR §11).
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::Paid => 'Paid / Confirmed',
            self::Processing => 'Processing',
            self::Dispatched => 'Dispatched',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::OnHold => 'On Hold',
        };
    }

    /**
     * Filament badge colour alias (used by the admin panel's built-in
     * colour palette, not a raw Tailwind class).
     */
    public function color(): string
    {
        return match ($this) {
            self::PendingPayment => 'gray',
            self::Paid => 'info',
            self::Processing => 'warning',
            self::Dispatched => 'primary',
            self::Delivered => 'success',
            self::Completed => 'success',
            self::Cancelled => 'danger',
            self::OnHold => 'danger',
        };
    }

    /**
     * Complete, literal Tailwind utility classes for storefront status
     * badges. Written out in full (rather than interpolated) so Tailwind's
     * content scanner can detect them - see resources/views/storefront.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PendingPayment => 'bg-gray-100 text-gray-700',
            self::Paid => 'bg-sky-100 text-sky-700',
            self::Processing => 'bg-amber-100 text-amber-700',
            self::Dispatched => 'bg-indigo-100 text-indigo-700',
            self::Delivered => 'bg-emerald-100 text-emerald-700',
            self::Completed => 'bg-emerald-100 text-emerald-700',
            self::Cancelled => 'bg-red-100 text-red-700',
            self::OnHold => 'bg-red-100 text-red-700',
        };
    }

    /**
     * Customer-facing order tracking timeline (TOR §8.1/§8.2 order tracking
     * timeline). Cancelled/OnHold are shown separately, not on the happy path.
     */
    public static function timeline(): array
    {
        return [
            self::PendingPayment,
            self::Paid,
            self::Processing,
            self::Dispatched,
            self::Delivered,
            self::Completed,
        ];
    }

    /**
     * System-enforced valid forward transitions for the happy path plus the
     * cancellation/on-hold branches described in TOR §6.4 and §11.
     *
     * @return array<string>
     */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Processing, self::OnHold, self::Cancelled],
            self::Processing => [self::Dispatched, self::OnHold, self::Cancelled],
            self::OnHold => [self::Processing, self::Cancelled],
            self::Dispatched => [self::Delivered],
            self::Delivered => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStatuses(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}

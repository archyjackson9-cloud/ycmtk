<?php

namespace App\Enums;

/**
 * Customer emails sent about an order: one per status the order can move
 * into, plus "placed" and "payment not completed" (which don't change the
 * status). Kept separate from OrderEventType so adding a status email
 * (e.g. Completed) does not change SMS templates or in-app notifications.
 * Values double as the NotificationLog `type`, matching OrderEventType
 * values where an equivalent exists.
 */
enum OrderEmailType: string
{
    case Placed = 'order_placed';
    case Confirmed = 'order_confirmed';
    case Processing = 'order_processing';
    case Dispatched = 'order_dispatched';
    case Delivered = 'order_delivered';
    case Completed = 'order_completed';
    case Cancelled = 'order_cancelled';
    case OnHold = 'order_on_hold';
    case PaymentFailed = 'payment_failed';

    public static function forStatus(OrderStatus $status): ?self
    {
        return match ($status) {
            OrderStatus::PendingPayment => null,
            OrderStatus::Paid => self::Confirmed,
            OrderStatus::Processing => self::Processing,
            OrderStatus::Dispatched => self::Dispatched,
            OrderStatus::Delivered => self::Delivered,
            OrderStatus::Completed => self::Completed,
            OrderStatus::Cancelled => self::Cancelled,
            OrderStatus::OnHold => self::OnHold,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Placed => 'Order Received',
            self::Confirmed => 'Order Confirmed',
            self::Processing => 'Order Being Prepared',
            self::Dispatched => 'Order Out for Delivery',
            self::Delivered => 'Order Delivered',
            self::Completed => 'Order Completed',
            self::Cancelled => 'Order Cancelled',
            self::OnHold => 'Order On Hold',
            self::PaymentFailed => 'Payment Not Completed',
        };
    }
}

<?php

namespace App\Enums;

/**
 * Order-lifecycle events that trigger an SMS notification (TOR §6.6,
 * mandatory SMS triggers list).
 */
enum OrderEventType: string
{
    case OrderPlaced = 'order_placed';
    case PaymentReceived = 'payment_received';
    case OrderConfirmed = 'order_confirmed';
    case OrderProcessing = 'order_processing';
    case OrderDispatched = 'order_dispatched';
    case OrderDelivered = 'order_delivered';
    case OrderCancelled = 'order_cancelled';
    case OrderOnHold = 'order_on_hold';
    case PaymentFailed = 'payment_failed';

    public function label(): string
    {
        return match ($this) {
            self::OrderPlaced => 'New Order',
            self::PaymentReceived => 'Payment Received',
            self::OrderConfirmed => 'Order Confirmed',
            self::OrderProcessing => 'Order Processing',
            self::OrderDispatched => 'Order Dispatched',
            self::OrderDelivered => 'Order Delivered',
            self::OrderCancelled => 'Order Cancelled',
            self::OrderOnHold => 'Order On Hold',
            self::PaymentFailed => 'Payment Failed',
        };
    }
}

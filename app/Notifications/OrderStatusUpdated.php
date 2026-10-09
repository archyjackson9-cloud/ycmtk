<?php

namespace App\Notifications;

use App\Enums\OrderEventType;
use App\Models\Order;
use Illuminate\Notifications\Notification;

/**
 * In-app (bell) notification for a registered customer whenever one of
 * their orders changes status - the same event set as the SMS/email
 * triggers (TOR §6.6), sent alongside them from OrderService::notify().
 * Guests have no account to show a bell in, so this only ever goes to
 * $order->user - see ShareStorefrontData for how the storefront reads it.
 */
class OrderStatusUpdated extends Notification
{
    public function __construct(public Order $order, public OrderEventType $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'event' => $this->event->value,
            'title' => $this->event->label(),
            'message' => $this->message(),
            'url' => route('orders.show', $this->order),
        ];
    }

    protected function message(): string
    {
        return match ($this->event) {
            OrderEventType::OrderPlaced => "Order {$this->order->order_number} has been received.",
            OrderEventType::PaymentReceived => "Payment received for order {$this->order->order_number}.",
            OrderEventType::OrderConfirmed => "Order {$this->order->order_number} is confirmed and queued for processing.",
            OrderEventType::OrderProcessing => "Order {$this->order->order_number} is being prepared.",
            OrderEventType::OrderDispatched => "Order {$this->order->order_number} is on its way.",
            OrderEventType::OrderDelivered => "Order {$this->order->order_number} has been delivered.",
            OrderEventType::OrderCancelled => "Order {$this->order->order_number} was cancelled.",
            OrderEventType::OrderOnHold => "Order {$this->order->order_number} needs your attention.",
            OrderEventType::PaymentFailed => "Payment for order {$this->order->order_number} was not completed.",
        };
    }
}

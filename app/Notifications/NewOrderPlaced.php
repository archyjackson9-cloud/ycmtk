<?php

namespace App\Notifications;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Notifications\Notification;

/**
 * In-app (bell) notification for staff (Super Admin, Inventory Officer)
 * whenever a customer places an order - shown via the Filament admin
 * panel's built-in database-notifications bell (AdminPanelProvider).
 * Particularly important for a pay-by-transfer order, since nothing else
 * prompts staff to go confirm it (see ManualPaymentService) until they
 * happen to check Admin > Payments.
 */
class NewOrderPlaced extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $payment = $this->order->latestPayment();

        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'title' => 'New order placed',
            'message' => sprintf(
                '%s placed order %s - %s%s%s',
                $this->order->customerName(),
                $this->order->order_number,
                config('cymarket.currency_symbol'),
                number_format((float) $this->order->total, 2),
                $payment?->provider === 'manual' ? ' (pay by transfer - awaiting confirmation)' : ''
            ),
            'url' => OrderResource::getUrl('view', ['record' => $this->order]),
        ];
    }
}

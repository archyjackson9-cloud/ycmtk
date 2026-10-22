<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * Presentation helper: turns an order's status + milestone timestamps into
 * the steps of the progress bar on the admin order page. Read-only; the
 * status machine itself stays in OrderStatus / OrderService.
 */
class OrderTimeline
{
    /**
     * @return array{steps: array<int, array{label: string, state: string, at: ?string}>, banner: ?array{label: string, tone: string, text: ?string}}
     */
    public static function for(Order $order): array
    {
        $happy = OrderStatus::timeline();
        $currentIndex = array_search($order->status, $happy, true);

        $timestamps = [
            OrderStatus::PendingPayment->value => $order->placed_at ?? $order->created_at,
            OrderStatus::Paid->value => $order->paid_at,
            OrderStatus::Processing->value => $order->processing_at,
            OrderStatus::Dispatched->value => $order->dispatched_at,
            OrderStatus::Delivered->value => $order->delivered_at,
            OrderStatus::Completed->value => $order->completed_at,
        ];

        $interrupted = in_array($order->status, [OrderStatus::Cancelled, OrderStatus::OnHold], true);

        $steps = [];
        foreach ($happy as $index => $status) {
            $at = $timestamps[$status->value] ?? null;

            $state = match (true) {
                $interrupted => $at ? 'done' : 'upcoming',
                $currentIndex !== false && $index < $currentIndex => 'done',
                $currentIndex !== false && $index === $currentIndex => ($status->isTerminal() ? 'done' : 'current'),
                default => 'upcoming',
            };

            $steps[] = [
                'label' => $status === OrderStatus::Paid ? 'Paid' : $status->label(),
                'state' => $state,
                'at' => $at?->format('d M, H:i'),
            ];
        }

        $banner = match ($order->status) {
            OrderStatus::Cancelled => ['label' => 'Cancelled', 'tone' => 'danger', 'text' => $order->cancelled_reason],
            OrderStatus::OnHold => ['label' => 'On hold', 'tone' => 'warning', 'text' => $order->on_hold_reason],
            default => null,
        };

        return ['steps' => $steps, 'banner' => $banner];
    }
}

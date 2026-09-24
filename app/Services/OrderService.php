<?php

namespace App\Services;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundPreference;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Services\Notifications\SmsNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Order Management & Lifecycle Service (TOR §5.2, §6.4). Owns every
 * status transition, keeps the audit trail (order_status_histories),
 * fires the mandatory SMS triggers (TOR §6.6), and implements the
 * fallback behaviours from TOR §11.
 */
class OrderService
{
    public function __construct(
        protected StockService $stock,
        protected SmsNotificationService $sms,
    ) {}

    /**
     * TOR §11 "Payment successful but stock insufficient (race condition)
     * -> Order marked 'On Hold'. Customer notified via SMS and given a
     * choice: full refund or apply payment to next available delivery
     * (preference recorded). Inventory Officer alerted."
     */
    public function markPaid(Payment $payment): Order
    {
        return DB::transaction(function () use ($payment) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->first();

            // Idempotency: webhook replays or duplicate callbacks must not
            // re-run fulfilment side effects (TOR §11 duplicate payment).
            if (in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Dispatched, OrderStatus::Delivered, OrderStatus::Completed], true)) {
                return $order;
            }

            $stockOk = $this->stock->consumeForOrder($order);

            if (! $stockOk) {
                $this->transition($order, OrderStatus::OnHold, note: 'Payment succeeded but stock became insufficient after checkout.');
                $order->update(['on_hold_reason' => 'One or more items sold out after your payment was received.']);
                $this->sms->sendOrderEvent($order->fresh(), OrderEventType::OrderOnHold);
                $this->notifyInventoryOfficers($order, 'Order '.$order->order_number.' is ON HOLD - insufficient stock after payment.');

                return $order->fresh();
            }

            // Payment confirmed -> order accepted into the fulfilment queue
            // (TOR §6.4). The Inventory Officer then moves it to Processing
            // themselves once they start preparing it - "all fulfilment is
            // handled directly by CY-Market" (TOR §6.4).
            $this->transition($order, OrderStatus::Paid, note: 'Payment confirmed by MTN MoMo.');
            $order->update(['paid_at' => now()]);

            $this->sms->sendOrderEvent($order->fresh(), OrderEventType::PaymentReceived);
            $this->sms->sendOrderEvent($order->fresh(), OrderEventType::OrderConfirmed);

            return $order->fresh();
        });
    }

    /**
     * TOR §11 "Payment gateway timeout / failure -> Cart preserved.
     * Customer shown a clear retry option. No order created." The Order
     * row already exists (needed to hold the stock reservation), so
     * "no order created" is honoured by releasing the reservation and
     * leaving status at PendingPayment so the customer can retry payment
     * from the same order rather than starting over.
     */
    public function markPaymentFailed(Payment $payment): Order
    {
        return DB::transaction(function () use ($payment) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->first();

            if ($order->status === OrderStatus::PendingPayment) {
                $this->stock->releaseForOrder($order, 'Payment attempt failed.');
            }

            $this->sms->sendOrderEvent($order, OrderEventType::PaymentFailed);

            return $order->fresh();
        });
    }

    /**
     * System-enforced status update (TOR §6.4 status machine). Used by the
     * Inventory Officer / Super Admin from the admin panel.
     */
    public function updateStatus(Order $order, OrderStatus $status, ?User $actor = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $status, $actor, $note) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $order->status->canTransitionTo($status)) {
                throw new RuntimeException("Cannot move an order from {$order->status->label()} to {$status->label()}.");
            }

            $this->transition($order, $status, $actor, $note);

            $timestampColumn = match ($status) {
                OrderStatus::Dispatched => 'dispatched_at',
                OrderStatus::Delivered => 'delivered_at',
                OrderStatus::Completed => 'completed_at',
                OrderStatus::Processing => 'processing_at',
                default => null,
            };

            if ($timestampColumn) {
                $order->update([$timestampColumn => now()]);
            }

            $event = match ($status) {
                OrderStatus::Processing => OrderEventType::OrderProcessing,
                OrderStatus::Dispatched => OrderEventType::OrderDispatched,
                OrderStatus::Delivered => OrderEventType::OrderDelivered,
                default => null,
            };

            if ($event) {
                $this->sms->sendOrderEvent($order->fresh(), $event);
            }

            return $order->fresh();
        });
    }

    /**
     * TOR §11 "Order cancelled after payment -> Automatic refund trigger
     * via MTN MoMo (or manual process with audit trail)."
     */
    public function cancel(Order $order, string $reason, ?User $actor = null, bool $notify = true): Order
    {
        return DB::transaction(function () use ($order, $reason, $actor, $notify) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $order->status->canTransitionTo(OrderStatus::Cancelled)) {
                throw new RuntimeException("Order {$order->order_number} can no longer be cancelled from status {$order->status->label()}.");
            }

            $wasPaid = in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::OnHold], true)
                && $order->payments()->where('status', PaymentStatus::Successful)->exists();

            if ($order->status === OrderStatus::PendingPayment) {
                $this->stock->releaseForOrder($order, $reason);
            } elseif ($wasPaid) {
                $this->stock->restockForOrder($order, "Restocked - order {$order->order_number} cancelled: {$reason}");
            }

            $this->transition($order, OrderStatus::Cancelled, $actor, $reason);
            $order->update(['cancelled_reason' => $reason, 'cancelled_at' => now()]);

            if ($wasPaid) {
                Log::channel('momo')->info('Refund required for cancelled paid order', [
                    'order' => $order->order_number,
                    'amount' => (float) $order->total,
                ]);
                // A real MTN MoMo refund API call would be issued here once
                // live credentials are configured; logged for the
                // reconciliation job/manual process in the meantime
                // (TOR §11).
            }

            if ($notify) {
                $this->sms->sendOrderEvent($order->fresh(), OrderEventType::OrderCancelled);
            }

            return $order->fresh();
        });
    }

    /**
     * Resolve an On-Hold order once the customer has chosen a preference
     * (TOR §6.5/§11 refund-vs-credit choice).
     */
    public function resolveOnHold(Order $order, RefundPreference $preference, ?User $actor = null): Order
    {
        $order->update(['refund_or_credit_preference' => $preference]);

        if ($preference === RefundPreference::Refund) {
            return $this->cancel($order, 'Customer chose a full refund after stock shortfall.', $actor);
        }

        // Credit for next-day delivery: Inventory Officer resumes the
        // order into Processing once restocked.
        return $this->updateStatus($order, OrderStatus::Processing, $actor, 'Customer chose credit for next-day delivery; resuming fulfilment.');
    }

    protected function transition(Order $order, OrderStatus $to, ?User $actor = null, ?string $note = null): void
    {
        $from = $order->status;

        $order->update(['status' => $to]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'changed_by' => $actor?->id,
        ]);
    }

    protected function notifyInventoryOfficers(Order $order, string $message): void
    {
        $phone = app(SettingsService::class)->get('internal_alert_phone');

        if ($phone) {
            $this->sms->send($phone, $message, OrderEventType::OrderOnHold, $order);
        }

        Log::warning($message, ['order_id' => $order->id]);
    }
}

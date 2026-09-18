<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

/**
 * Inventory control, including the soft stock-reservation safeguard from
 * TOR §11 ("Additional recommended safeguards - Soft stock reservation at
 * 'Payment Initiated' stage with a short timeout" and "High concurrent
 * orders on popular items -> Optimistic locking or stock reservation at
 * payment-confirmation time").
 */
class StockService
{
    public function __construct(protected SettingsService $settings) {}

    /**
     * Soft-reserve every line item of an order for a short window while the
     * customer completes payment. Uses a row lock per product to stay safe
     * under concurrent checkouts of the same popular item.
     */
    public function reserveForOrder(Order $order): void
    {
        $minutes = $this->settings->stockReservationMinutes();

        DB::transaction(function () use ($order, $minutes) {
            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                /** @var Product $product */
                $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                $product->increment('reserved_quantity', $item->quantity);

                StockReservation::create([
                    'product_id' => $product->id,
                    'order_id' => $order->id,
                    'quantity' => $item->quantity,
                    'status' => 'active',
                    'expires_at' => now()->addMinutes($minutes),
                ]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => StockMovementType::Reserved,
                    'quantity' => $item->quantity,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => "Reserved for order {$order->order_number} pending payment.",
                ]);
            }
        });
    }

    /**
     * Release an order's active reservations without deducting stock -
     * used when payment fails/expires/is cancelled before fulfilment.
     */
    public function releaseForOrder(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $reservations = StockReservation::where('order_id', $order->id)->active()->lockForUpdate()->get();

            foreach ($reservations as $reservation) {
                $product = Product::whereKey($reservation->product_id)->lockForUpdate()->first();

                if ($product) {
                    $product->decrement('reserved_quantity', min($reservation->quantity, $product->reserved_quantity));

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => StockMovementType::ReservationReleased,
                        'quantity' => $reservation->quantity,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'note' => $reason,
                    ]);
                }

                $reservation->update(['status' => 'released']);
            }
        });
    }

    /**
     * Payment confirmed: convert the soft reservation into a real stock
     * deduction. Returns false (and leaves stock untouched) if, despite the
     * reservation, on-hand stock is somehow insufficient - the race
     * condition explicitly called out in TOR §11, which the caller
     * (OrderService::markPaid) handles by placing the order On Hold.
     */
    public function consumeForOrder(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $insufficient = false;

            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();

                if (! $product || $product->available_quantity < $item->quantity) {
                    $insufficient = true;
                    break;
                }
            }

            if ($insufficient) {
                return false;
            }

            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                $product->decrement('available_quantity', $item->quantity);
                $product->decrement('reserved_quantity', min($item->quantity, $product->reserved_quantity));
                $product->increment('sold_count', $item->quantity);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => StockMovementType::Out,
                    'quantity' => $item->quantity,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => "Sold via order {$order->order_number}.",
                ]);
            }

            StockReservation::where('order_id', $order->id)->active()->update(['status' => 'consumed']);

            return true;
        });
    }

    /**
     * Restock previously consumed items when a paid order is cancelled
     * (TOR §11 "Order cancelled after payment -> Automatic refund trigger
     * ... Customer preference for credit vs refund respected").
     */
    public function restockForOrder(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();

                if (! $product) {
                    continue;
                }

                $product->increment('available_quantity', $item->quantity);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => StockMovementType::In,
                    'quantity' => $item->quantity,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => $reason,
                ]);
            }
        });
    }

    public function manualAdjustment(Product $product, int $delta, string $note, ?int $actorId = null): void
    {
        DB::transaction(function () use ($product, $delta, $note, $actorId) {
            $product = Product::whereKey($product->id)->lockForUpdate()->first();
            $product->increment('available_quantity', $delta);

            StockMovement::create([
                'product_id' => $product->id,
                'type' => $delta >= 0 ? StockMovementType::In : StockMovementType::Adjustment,
                'quantity' => abs($delta),
                'reference_type' => 'manual',
                'note' => $note,
                'user_id' => $actorId,
            ]);
        });
    }

    /**
     * Sweep reservations whose short payment window has lapsed (console
     * command, scheduled every minute - see routes/console.php).
     */
    public function releaseExpiredReservations(): int
    {
        $expired = StockReservation::expired()->whereNotNull('order_id')->get()->groupBy('order_id');
        $released = 0;

        foreach ($expired as $orderId => $reservations) {
            $order = Order::find($orderId);

            if (! $order) {
                continue;
            }

            if ($order->status === OrderStatus::PendingPayment) {
                // Reservation window lapsed before payment completed -
                // release the hold and cancel the order (TOR §11 soft
                // reservation safeguard).
                app(OrderService::class)->cancel($order, 'Payment was not completed within the reservation window.', notify: true);
            } else {
                // Order has since moved on (paid, already cancelled, ...) -
                // just clear the now-stale reservation rows.
                $this->releaseForOrder($order, 'Stock reservation window expired.');
            }

            $released += $reservations->count();
        }

        return $released;
    }
}

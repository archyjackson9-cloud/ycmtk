<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Sample orders across every lifecycle stage (TOR §6.4 Order Lifecycle
 * Management, §6.11 Reporting dashboard) so the admin dashboard, reports,
 * order tracking and Filament OrderResource all have something realistic
 * to show immediately after a fresh install - not just an empty table.
 *
 * Built directly with Eloquent (not through CheckoutService) since these
 * orders are meant to already exist in various finished/in-flight states;
 * going through the live checkout flow would only reproduce the same data
 * with far more seeder complexity for no extra test coverage.
 */
class OrderSeeder extends Seeder
{
    /** Statuses where payment has succeeded and stock was actually taken. */
    protected function stockDeductingStatuses(): array
    {
        return [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Dispatched, OrderStatus::Delivered, OrderStatus::Completed, OrderStatus::OnHold];
    }

    public function run(): void
    {
        $customers = User::role(config('cymarket.roles.customer'))->get();
        $inventoryOfficer = User::role(config('cymarket.roles.inventory_officer'))->first();
        $superAdmin = User::role(config('cymarket.roles.super_admin'))->first();
        $zones = DeliveryZone::all();
        $products = Product::where('is_active', true)->where('available_quantity', '>', 0)->get();

        if ($customers->isEmpty() || $products->isEmpty() || $zones->isEmpty()) {
            $this->command?->warn('Skipping OrderSeeder - run UserSeeder, DeliveryZoneSeeder and ProductSeeder first.');

            return;
        }

        // One deterministic order per status, so every lifecycle stage is
        // guaranteed to be represented for demo/testing purposes.
        foreach (OrderStatus::cases() as $index => $status) {
            $this->createOrder(
                status: $status,
                user: $index % 3 === 0 ? null : $customers->random(), // mix of guest and registered
                products: $products,
                zones: $zones,
                inventoryOfficer: $inventoryOfficer,
                superAdmin: $superAdmin,
                daysAgo: random_int(1, 20),
            );
        }

        // A handful of extra random orders, weighted toward the happy path,
        // so reports/charts have more than eight data points to show.
        $weightedStatuses = [
            OrderStatus::Completed, OrderStatus::Completed, OrderStatus::Delivered, OrderStatus::Delivered,
            OrderStatus::Processing, OrderStatus::Dispatched, OrderStatus::Paid, OrderStatus::PendingPayment,
            OrderStatus::Completed, OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Processing,
        ];

        foreach ($weightedStatuses as $status) {
            $this->createOrder(
                status: $status,
                user: random_int(0, 4) === 0 ? null : $customers->random(),
                products: $products,
                zones: $zones,
                inventoryOfficer: $inventoryOfficer,
                superAdmin: $superAdmin,
                daysAgo: random_int(0, 25),
            );
        }

        $this->command?->info('Seeded '.Order::count().' sample orders.');
    }

    protected function createOrder(
        OrderStatus $status,
        ?User $user,
        $products,
        $zones,
        ?User $inventoryOfficer,
        ?User $superAdmin,
        int $daysAgo,
    ): void {
        $zone = $zones->random();
        $placedAt = Carbon::now()->subDays($daysAgo)->subHours(random_int(0, 20))->subMinutes(random_int(0, 59));

        $recipientName = $user?->name ?? fake()->name();
        $recipientPhone = $user?->phone ?? ('+2332'.fake()->numerify('########'));

        $itemCount = random_int(1, 4);
        $orderProducts = $products->random(min($itemCount, $products->count()));
        $orderProducts = $orderProducts instanceof Product ? collect([$orderProducts]) : $orderProducts;

        $lineItems = $orderProducts->map(function (Product $product) {
            $quantity = random_int(1, 5);

            return [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => (float) $product->selling_price,
                'line_total' => round((float) $product->selling_price * $quantity, 2),
            ];
        });

        $subtotal = round($lineItems->sum('line_total'), 2);
        $deliveryFee = (float) $zone->fee();
        $total = round($subtotal + $deliveryFee, 2);

        $order = Order::create([
            'user_id' => $user?->id,
            'status' => $status,
            'guest_name' => $user ? null : $recipientName,
            'guest_phone' => $user ? null : $recipientPhone,
            'guest_email' => $user ? null : fake()->safeEmail(),
            'delivery_recipient_name' => $recipientName,
            'delivery_phone' => $recipientPhone,
            'delivery_zone_id' => $zone->id,
            'delivery_address_line' => fake()->numberBetween(1, 99).' '.fake()->streetName().', '.$zone->name,
            'delivery_landmark' => fake()->optional(0.6)->randomElement(['Near the lorry station', 'Opposite the market', 'Behind the clinic']),
            'delivery_fee' => $deliveryFee,
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'total' => $total,
            'notes' => fake()->optional(0.2)->sentence(),
        ]);
        $this->backdate($order, $placedAt);

        foreach ($lineItems as $item) {
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product']->id,
                'product_name' => $item['product']->name,
                'sku' => $item['product']->sku,
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'line_total' => $item['line_total'],
            ]);
            $this->backdate($orderItem, $placedAt);
        }

        $timestamps = $this->applyLifecycleTimestamps($order, $status, $placedAt);
        $order->update($timestamps);

        $this->recordStatusHistory($order, $status, $placedAt, $inventoryOfficer, $superAdmin);

        if (in_array($status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Dispatched, OrderStatus::Delivered, OrderStatus::Completed, OrderStatus::OnHold], true)) {
            $paymentTime = $timestamps['paid_at'] ?? $placedAt;

            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'mtn_momo',
                'channel' => fake()->randomElement(['mtn-momo']),
                'amount' => $order->total,
                'status' => PaymentStatus::Successful,
                'raw_response' => ['mode' => 'sandbox', 'seeded' => true],
                'paid_at' => $paymentTime,
            ]);
            $this->backdate($payment, $paymentTime);
        } elseif ($status === OrderStatus::Cancelled && random_int(0, 1) === 1) {
            // Some cancellations are a failed payment attempt, not an
            // active choice - demonstrates the "Payment failed" fallback.
            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'mtn_momo',
                'channel' => fake()->randomElement(['mtn-momo']),
                'amount' => $order->total,
                'status' => PaymentStatus::Failed,
                'raw_response' => ['mode' => 'sandbox', 'seeded' => true, 'reason' => 'insufficient_funds'],
            ]);
            $this->backdate($payment, $placedAt);
        }

        if (in_array($status, $this->stockDeductingStatuses(), true)) {
            foreach ($lineItems as $item) {
                /** @var Product $product */
                $product = $item['product'];
                $deduct = min($item['quantity'], $product->available_quantity);

                if ($deduct <= 0) {
                    continue;
                }

                $product->decrement('available_quantity', $deduct);
                $product->increment('sold_count', $deduct);

                $movement = StockMovement::create([
                    'product_id' => $product->id,
                    'type' => StockMovementType::Out,
                    'quantity' => $deduct,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => "Sold via order {$order->order_number}",
                    'user_id' => $inventoryOfficer?->id,
                ]);
                $this->backdate($movement, $timestamps['paid_at'] ?? $placedAt);
            }
        }
    }

    protected function applyLifecycleTimestamps(Order $order, OrderStatus $status, Carbon $placedAt): array
    {
        $timestamps = ['placed_at' => $placedAt];

        if ($status === OrderStatus::Cancelled) {
            $timestamps['cancelled_at'] = (clone $placedAt)->addMinutes(random_int(5, 180));
            $timestamps['cancelled_reason'] = fake()->randomElement([
                'Customer requested cancellation before dispatch.',
                'Payment could not be completed in time.',
                'Delivery address was outside the pilot coverage zone.',
            ]);

            return $timestamps;
        }

        if ($status === OrderStatus::PendingPayment) {
            return $timestamps;
        }

        $paidAt = (clone $placedAt)->addMinutes(random_int(2, 20));
        $timestamps['paid_at'] = $paidAt;

        if ($status === OrderStatus::OnHold) {
            $timestamps['on_hold_reason'] = 'Stock fell short after payment - awaiting Super Admin refund/credit decision.';

            return $timestamps;
        }

        if ($status === OrderStatus::Paid) {
            return $timestamps;
        }

        $processingAt = (clone $paidAt)->addHours(random_int(1, 6));
        $timestamps['processing_at'] = $processingAt;

        if ($status === OrderStatus::Processing) {
            return $timestamps;
        }

        $dispatchedAt = (clone $processingAt)->addHours(random_int(2, 10));
        $timestamps['dispatched_at'] = $dispatchedAt;

        if ($status === OrderStatus::Dispatched) {
            return $timestamps;
        }

        $deliveredAt = (clone $dispatchedAt)->addHours(random_int(1, 24));
        $timestamps['delivered_at'] = $deliveredAt;

        if ($status === OrderStatus::Delivered) {
            return $timestamps;
        }

        $timestamps['completed_at'] = (clone $deliveredAt)->addDays(random_int(0, 2));

        return $timestamps;
    }

    protected function recordStatusHistory(Order $order, OrderStatus $finalStatus, Carbon $placedAt, ?User $inventoryOfficer, ?User $superAdmin): void
    {
        $chain = match ($finalStatus) {
            OrderStatus::PendingPayment => [[null, OrderStatus::PendingPayment, null]],
            OrderStatus::Paid => [[null, OrderStatus::PendingPayment, null], [OrderStatus::PendingPayment, OrderStatus::Paid, null]],
            OrderStatus::OnHold => [[null, OrderStatus::PendingPayment, null], [OrderStatus::PendingPayment, OrderStatus::Paid, null], [OrderStatus::Paid, OrderStatus::OnHold, $superAdmin]],
            OrderStatus::Processing => [[null, OrderStatus::PendingPayment, null], [OrderStatus::PendingPayment, OrderStatus::Paid, null], [OrderStatus::Paid, OrderStatus::Processing, $inventoryOfficer]],
            OrderStatus::Dispatched => [[null, OrderStatus::PendingPayment, null], [OrderStatus::PendingPayment, OrderStatus::Paid, null], [OrderStatus::Paid, OrderStatus::Processing, $inventoryOfficer], [OrderStatus::Processing, OrderStatus::Dispatched, $inventoryOfficer]],
            OrderStatus::Delivered => [[null, OrderStatus::PendingPayment, null], [OrderStatus::PendingPayment, OrderStatus::Paid, null], [OrderStatus::Paid, OrderStatus::Processing, $inventoryOfficer], [OrderStatus::Processing, OrderStatus::Dispatched, $inventoryOfficer], [OrderStatus::Dispatched, OrderStatus::Delivered, $inventoryOfficer]],
            OrderStatus::Completed => [[null, OrderStatus::PendingPayment, null], [OrderStatus::PendingPayment, OrderStatus::Paid, null], [OrderStatus::Paid, OrderStatus::Processing, $inventoryOfficer], [OrderStatus::Processing, OrderStatus::Dispatched, $inventoryOfficer], [OrderStatus::Dispatched, OrderStatus::Delivered, $inventoryOfficer], [OrderStatus::Delivered, OrderStatus::Completed, null]],
            OrderStatus::Cancelled => [[null, OrderStatus::PendingPayment, null], [OrderStatus::PendingPayment, OrderStatus::Cancelled, $superAdmin]],
        };

        $timestamp = clone $placedAt;

        foreach ($chain as [$from, $to, $actor]) {
            $history = OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $actor ? 'Updated from the admin dashboard.' : null,
                'changed_by' => $actor?->id,
            ]);
            $this->backdate($history, $timestamp);

            $timestamp = (clone $timestamp)->addMinutes(random_int(10, 240));
        }
    }

    /**
     * Force a model's timestamps to a historical value after creation -
     * created_at/updated_at aren't mass-assignable via each model's
     * $fillable, so Eloquent would otherwise stamp every seeded row with
     * "now" regardless of the order's simulated placement date.
     */
    protected function backdate($model, Carbon $timestamp): void
    {
        $model->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->save();
    }
}

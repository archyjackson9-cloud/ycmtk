<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\StockReservation;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * Soft stock reservation & the payment-succeeded-but-stock-insufficient
 * race condition (TOR §11 "Additional recommended safeguards - Soft stock
 * reservation at 'Payment Initiated' stage with a short timeout" and
 * "High concurrent orders on popular items ... Payment successful but
 * stock insufficient (race condition) -> Order marked 'On Hold'").
 */
class StockReservationTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function pendingOrderFor($product, int $quantity, $zone): Order
    {
        $order = Order::create([
            'status' => OrderStatus::PendingPayment,
            'delivery_recipient_name' => 'Test Customer',
            'delivery_phone' => '+233200000001',
            'delivery_zone_id' => $zone->id,
            'delivery_address_line' => '1 Test Street',
            'delivery_fee' => (float) $zone->fee_override,
            'subtotal' => $product->selling_price * $quantity,
            'total' => $product->selling_price * $quantity + (float) $zone->fee_override,
            'placed_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => $product->selling_price,
            'quantity' => $quantity,
            'line_total' => $product->selling_price * $quantity,
        ]);

        return $order->fresh('items');
    }

    public function test_reserving_stock_increases_reserved_quantity_without_touching_available_quantity(): void
    {
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10, 'reserved_quantity' => 0]);
        $order = $this->pendingOrderFor($product, 4, $zone);

        app(StockService::class)->reserveForOrder($order);

        $this->assertSame(4, $product->fresh()->reserved_quantity);
        $this->assertSame(10, $product->fresh()->available_quantity);
        $this->assertSame(6, $product->fresh()->sellable_quantity, 'Sellable quantity is available minus reserved.');
        $this->assertSame(1, StockReservation::where('order_id', $order->id)->active()->count());
    }

    public function test_a_second_order_on_the_last_units_is_placed_on_hold_when_stock_runs_out(): void
    {
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 5, 'reserved_quantity' => 0]);

        $orderA = $this->pendingOrderFor($product, 5, $zone);
        $orderB = $this->pendingOrderFor($product, 5, $zone);

        $stock = app(StockService::class);
        $orders = app(OrderService::class);

        // Both customers reserved before either paid (both saw "in stock").
        $stock->reserveForOrder($orderA);
        $stock->reserveForOrder($orderB);
        $this->assertSame(10, $product->fresh()->reserved_quantity);

        $paymentA = $orderA->payments()->create(['provider' => 'hubtel', 'amount' => $orderA->total, 'status' => PaymentStatus::Successful, 'paid_at' => now()]);
        $paymentB = $orderB->payments()->create(['provider' => 'hubtel', 'amount' => $orderB->total, 'status' => PaymentStatus::Successful, 'paid_at' => now()]);

        // Order A's payment confirms first - it succeeds and consumes all 5 units.
        $orders->markPaid($paymentA->fresh());
        $this->assertSame(OrderStatus::Paid, $orderA->fresh()->status);
        $this->assertSame(0, $product->fresh()->available_quantity);

        // Order B's payment confirms next - the race condition TOR §11
        // describes: it also succeeded, but nothing is left to fulfil it.
        $orders->markPaid($paymentB->fresh());
        $orderB->refresh();

        $this->assertSame(OrderStatus::OnHold, $orderB->status);
        $this->assertNotNull($orderB->on_hold_reason);
    }

    public function test_manual_stock_adjustment_is_audited(): void
    {
        $product = $this->makeProduct(['available_quantity' => 20]);
        $officer = $this->makeInventoryOfficer();

        app(StockService::class)->manualAdjustment($product, 10, 'Fresh delivery from the farm.', $officer->id);

        $this->assertSame(30, $product->fresh()->available_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 10,
            'user_id' => $officer->id,
        ]);
    }

    public function test_expired_reservation_releases_stock_and_cancels_the_order(): void
    {
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10, 'reserved_quantity' => 0]);
        $order = $this->pendingOrderFor($product, 3, $zone);

        app(StockService::class)->reserveForOrder($order);
        // Force the reservation into the past so the sweep picks it up.
        StockReservation::where('order_id', $order->id)->update(['expires_at' => now()->subMinutes(5)]);

        $released = app(StockService::class)->releaseExpiredReservations();

        $this->assertSame(1, $released);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(0, $product->fresh()->reserved_quantity);
    }
}

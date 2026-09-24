<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * Order status machine (TOR §6.4 Order Lifecycle Management - the system
 * only allows the transitions OrderStatus::allowedNextStatuses() defines,
 * every change is audited, and cancelling a paid order restocks it).
 */
class OrderLifecycleTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function makePaidOrder(int $quantity = 2): Order
    {
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10, 'selling_price' => 20]);

        $order = Order::create([
            'status' => OrderStatus::Paid,
            'delivery_recipient_name' => 'Test Customer',
            'delivery_phone' => '+233200000001',
            'delivery_zone_id' => $zone->id,
            'delivery_address_line' => '1 Test Street',
            'delivery_fee' => (float) $zone->fee_override,
            'subtotal' => 20 * $quantity,
            'total' => 20 * $quantity + (float) $zone->fee_override,
            'placed_at' => now(),
            'paid_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 20,
            'quantity' => $quantity,
            'line_total' => 20 * $quantity,
        ]);

        $order->payments()->create([
            'provider' => 'mtn_momo',
            'amount' => $order->total,
            'status' => PaymentStatus::Successful,
            'paid_at' => now(),
        ]);

        return $order->fresh(['items', 'payments']);
    }

    public function test_a_valid_status_transition_updates_the_order_and_records_history(): void
    {
        $order = $this->makePaidOrder();
        $officer = $this->makeInventoryOfficer();

        $updated = app(OrderService::class)->updateStatus($order, OrderStatus::Processing, $officer, 'Preparing the order.');

        $this->assertSame(OrderStatus::Processing, $updated->status);
        $this->assertNotNull($updated->processing_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::Paid->value,
            'to_status' => OrderStatus::Processing->value,
            'changed_by' => $officer->id,
        ]);
    }

    public function test_an_invalid_status_transition_is_rejected(): void
    {
        $order = $this->makePaidOrder();
        // Paid orders may not jump straight to Delivered.

        $this->expectException(RuntimeException::class);

        app(OrderService::class)->updateStatus($order, OrderStatus::Delivered);
    }

    public function test_cancelling_a_paid_order_restocks_the_items(): void
    {
        $order = $this->makePaidOrder(quantity: 3);
        $product = $order->items->first()->product;
        $startingStock = $product->available_quantity;

        app(OrderService::class)->cancel($order, 'Customer requested cancellation.', notify: false);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('Customer requested cancellation.', $order->cancelled_reason);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame($startingStock + 3, $product->fresh()->available_quantity);
    }

    public function test_a_terminal_order_cannot_be_cancelled_again(): void
    {
        $order = $this->makePaidOrder();
        app(OrderService::class)->cancel($order, 'First cancellation.', notify: false);

        $this->expectException(RuntimeException::class);

        app(OrderService::class)->cancel($order->fresh(), 'Second attempt.', notify: false);
    }

    public function test_completed_orders_have_no_further_allowed_transitions(): void
    {
        $order = $this->makePaidOrder();
        $order->update(['status' => OrderStatus::Completed]);

        $this->assertSame([], OrderStatus::Completed->allowedNextStatuses());
        $this->assertFalse($order->fresh()->status->canTransitionTo(OrderStatus::Processing));
    }
}

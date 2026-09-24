<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StockReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * End-to-end guest checkout through the sandbox payment gateway (TOR §6.3
 * "Cart -> Delivery -> Payment -> Confirmation", §6.5 Payments, §11
 * "Duplicate payment / double-click on pay -> Idempotency keys with
 * MoMo prevent duplicate charges"). Runs entirely against MOMO_MODE
 * =simulate (the config default) so no network access is required.
 */
class CheckoutFlowTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    public function test_guest_can_complete_checkout_and_stock_is_soft_reserved(): void
    {
        $zone = $this->makeDeliveryZone(['fee_override' => 15.00]);
        $product = $this->makeProduct(['selling_price' => 20.00, 'available_quantity' => 10]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

        $response = $this->post(route('checkout.store'), [
            'recipient_name' => 'Ama Serwaa',
            'phone' => '+233200000099',
            'delivery_zone_id' => $zone->id,
            'address_line' => '12 Station Road, Tarkwa',
            'guest_name' => 'Ama Serwaa',
            'guest_email' => 'ama@example.test',
        ]);

        $order = Order::firstOrFail();

        $response->assertRedirect(route('checkout.sandbox-pay', $order->latestPayment()->reference));
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertEquals(40.00, (float) $order->subtotal);
        $this->assertEquals(15.00, (float) $order->delivery_fee);
        $this->assertEquals(55.00, (float) $order->total);

        // Stock was soft-reserved, not yet deducted (TOR §11 safeguard).
        $this->assertSame(2, StockReservation::where('order_id', $order->id)->sum('quantity'));
        $this->assertSame(2, $product->fresh()->reserved_quantity);
        $this->assertSame(10, $product->fresh()->available_quantity);
    }

    public function test_approving_sandbox_payment_confirms_the_order_and_consumes_stock(): void
    {
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);
        $this->post(route('checkout.store'), [
            'recipient_name' => 'Kofi Adjei',
            'phone' => '+233200000098',
            'delivery_zone_id' => $zone->id,
            'address_line' => '5 Market Road, Tarkwa',
            'guest_name' => 'Kofi Adjei',
        ]);

        $order = Order::firstOrFail();
        $payment = $order->latestPayment();

        $response = $this->post(route('checkout.sandbox-confirm', $payment->reference), ['approve' => 1]);

        $response->assertRedirect(route('checkout.return', $payment->reference));
        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(PaymentStatus::Successful, $payment->fresh()->status);
        $this->assertSame(7, $product->fresh()->available_quantity, 'Stock should be consumed once payment is confirmed.');
        $this->assertSame(0, $product->fresh()->reserved_quantity, 'The soft reservation should be released once consumed.');
    }

    public function test_duplicate_payment_confirmation_does_not_double_charge_stock(): void
    {
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
        $this->post(route('checkout.store'), [
            'recipient_name' => 'Yaw Asante',
            'phone' => '+233200000097',
            'delivery_zone_id' => $zone->id,
            'address_line' => '9 Hospital Road, Tarkwa',
            'guest_name' => 'Yaw Asante',
        ]);

        $order = Order::firstOrFail();
        $payment = $order->latestPayment();

        // Simulate a double-click / webhook replay: confirm twice.
        $this->post(route('checkout.sandbox-confirm', $payment->reference), ['approve' => 1]);
        $this->post(route('checkout.sandbox-confirm', $payment->reference), ['approve' => 1]);

        $this->assertSame(8, $product->fresh()->available_quantity, 'Stock must only be deducted once despite the duplicate confirmation.');
        $this->assertSame(1, $order->statusHistories()->where('to_status', OrderStatus::Paid->value)->count());
    }

    public function test_declined_sandbox_payment_releases_the_stock_reservation(): void
    {
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 4]);
        $this->post(route('checkout.store'), [
            'recipient_name' => 'Abena Frimpong',
            'phone' => '+233200000096',
            'delivery_zone_id' => $zone->id,
            'address_line' => '3 Church Street, Tarkwa',
            'guest_name' => 'Abena Frimpong',
        ]);

        $order = Order::firstOrFail();
        $payment = $order->latestPayment();

        $this->post(route('checkout.sandbox-confirm', $payment->reference), ['approve' => 0]);

        $order->refresh();
        $this->assertSame(OrderStatus::PendingPayment, $order->status, 'A failed payment keeps the order at PendingPayment so the customer can retry.');
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(0, $product->fresh()->reserved_quantity, 'The reservation should be released on payment failure.');
        $this->assertSame(10, $product->fresh()->available_quantity);
    }
}

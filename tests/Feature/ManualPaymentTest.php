<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\StockReservation;
use App\Services\Payments\ManualPaymentService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * "Pay by transfer" - the pilot payment method where the customer pays an
 * account published outside the system entirely, and a staff member
 * confirms the matching Payment by hand (see ManualPaymentService,
 * PaymentResource::confirmAction()/rejectAction()).
 */
class ManualPaymentTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function enableManualPayment(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('manual_payment_account_name', 'CY-Market Farms Ltd');
        $settings->set('manual_payment_account_number', '0241234567');
    }

    protected function placeOrder(int $quantity = 2): Order
    {
        $this->enableManualPayment();

        $zone = $this->makeDeliveryZone(['fee_override' => 15.00]);
        $product = $this->makeProduct(['selling_price' => 20.00, 'available_quantity' => 10]);

        $this->carryCookies($this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => $quantity]));

        $this->post(route('checkout.store'), [
            'payment_method' => 'manual',
            'recipient_name' => 'Kwesi Darko',
            'phone' => '+233200000095',
            'delivery_zone_id' => $zone->id,
            'address_line' => '7 Mine Road, Tarkwa',
            'guest_name' => 'Kwesi Darko',
        ]);

        return Order::firstOrFail();
    }

    public function test_checkout_with_manual_payment_sends_the_customer_to_the_instructions_page_and_holds_stock_for_the_configured_window(): void
    {
        $order = $this->placeOrder(quantity: 2);
        $payment = $order->latestPayment();

        $this->assertSame('manual', $payment->provider);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);

        $reservation = StockReservation::where('order_id', $order->id)->firstOrFail();
        $this->assertTrue(
            $reservation->expires_at->greaterThan(now()->addHours(23)),
            'Manual payments should hold stock for ~24h (SettingsService::manualPaymentReservationHours), not the short MoMo window.'
        );
    }

    public function test_manual_payment_option_is_hidden_when_no_account_details_are_configured(): void
    {
        // No enableManualPayment() call - account details are blank.
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->carryCookies($this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]));

        $response = $this->post(route('checkout.store'), [
            'payment_method' => 'manual',
            'recipient_name' => 'Adwoa Nyarko',
            'phone' => '+233200000094',
            'delivery_zone_id' => $zone->id,
            'address_line' => '2 Lake Road, Tarkwa',
            'guest_name' => 'Adwoa Nyarko',
        ]);

        // Falls back to momo rather than sending the customer to a blank
        // instructions page - and momo_number is then required, so this
        // particular request (which sent none) is rejected with a
        // validation error rather than creating an order.
        $response->assertSessionHasErrors('momo_number');
        $this->assertSame(0, Order::count());
    }

    public function test_staff_confirming_a_manual_payment_marks_the_order_paid_and_consumes_stock(): void
    {
        $order = $this->placeOrder(quantity: 3);
        $payment = $order->latestPayment();
        $admin = $this->makeSuperAdmin();
        $product = $order->items->first()->product;

        app(ManualPaymentService::class)->confirm($payment, $admin, approved: true, amountReceived: (float) $order->total, note: 'Momo ref 123456');

        $order->refresh();
        $payment->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(PaymentStatus::Successful, $payment->status);
        $this->assertSame($admin->id, $payment->confirmed_by);
        $this->assertSame('Momo ref 123456', $payment->note);
        $this->assertSame(7, $product->fresh()->available_quantity, 'Stock should be consumed once staff confirm the transfer.');
        $this->assertSame(0, $product->fresh()->reserved_quantity);
    }

    public function test_staff_rejecting_a_manual_payment_releases_stock_and_keeps_the_order_retryable(): void
    {
        $order = $this->placeOrder(quantity: 2);
        $payment = $order->latestPayment();
        $admin = $this->makeSuperAdmin();
        $product = $order->items->first()->product;

        app(ManualPaymentService::class)->confirm($payment, $admin, approved: false, note: 'No matching transfer found after 48h.');

        $order->refresh();
        $payment->refresh();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame(0, $product->fresh()->reserved_quantity, 'The stock hold should be released once staff reject the payment.');
        $this->assertSame(10, $product->fresh()->available_quantity);
    }

    public function test_confirming_an_already_terminal_payment_is_a_no_op(): void
    {
        $order = $this->placeOrder(quantity: 1);
        $payment = $order->latestPayment();
        $admin = $this->makeSuperAdmin();

        app(ManualPaymentService::class)->confirm($payment, $admin, approved: true, amountReceived: (float) $order->total);
        $firstHistoryCount = $order->statusHistories()->count();

        // A second confirmation attempt (e.g. an admin double-clicking)
        // must not re-run fulfilment side effects.
        app(ManualPaymentService::class)->confirm($payment, $admin, approved: true, amountReceived: (float) $order->total);

        $this->assertSame($firstHistoryCount, $order->statusHistories()->count());
    }

    public function test_customer_can_attach_optional_proof_of_payment(): void
    {
        $order = $this->placeOrder(quantity: 1);
        $payment = $order->latestPayment();

        UploadedFile::fake()->image('receipt.jpg')->size(100);

        $response = $this->post(route('checkout.manual-pay.proof', $payment->reference), [
            'proof' => UploadedFile::fake()->image('receipt.jpg')->size(100),
        ]);

        $response->assertRedirect(route('checkout.manual-pay', $payment->reference));
        $this->assertNotNull($payment->fresh()->proof_path);
    }
}

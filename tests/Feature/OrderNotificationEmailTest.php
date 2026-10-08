<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\OrderEventType;
use App\Mail\OrderEventMail;
use App\Models\NotificationLog;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * Order-tracking-update emails (the email counterpart to every mandatory
 * SMS trigger, TOR §6.6) - sent via EmailNotificationService whenever an
 * order has an email address on file, logged to notification_logs exactly
 * like SMS so Super Admin can see delivery status for both channels.
 */
class OrderNotificationEmailTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function placeOrderWithEmail(): Order
    {
        $zone = $this->makeDeliveryZone(['fee_override' => 15.00]);
        $product = $this->makeProduct(['selling_price' => 20.00, 'available_quantity' => 10]);

        $this->carryCookies($this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]));

        $this->post(route('checkout.store'), [
            'payment_method' => 'momo',
            'momo_number' => '+233200000093',
            'recipient_name' => 'Akua Boadi',
            'phone' => '+233200000093',
            'delivery_zone_id' => $zone->id,
            'address_line' => '4 Hilltop Road, Tarkwa',
            'guest_name' => 'Akua Boadi',
            'guest_email' => 'akua@example.test',
        ]);

        return Order::firstOrFail();
    }

    public function test_placing_an_order_with_an_email_sends_a_tracking_update_email(): void
    {
        Mail::fake();

        $order = $this->placeOrderWithEmail();

        Mail::assertSent(OrderEventMail::class, fn (OrderEventMail $mail) => $mail->order->is($order) && $mail->event === OrderEventType::OrderPlaced);

        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $order->id,
            'channel' => NotificationChannel::Email->value,
            'type' => OrderEventType::OrderPlaced->value,
            'recipient' => 'akua@example.test',
        ]);
    }

    public function test_no_email_is_sent_when_the_order_has_no_email_address(): void
    {
        Mail::fake();

        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->carryCookies($this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]));
        $this->post(route('checkout.store'), [
            'payment_method' => 'momo',
            'momo_number' => '+233200000092',
            'recipient_name' => 'Kwame Antwi',
            'phone' => '+233200000092',
            'delivery_zone_id' => $zone->id,
            'address_line' => '6 Low Road, Tarkwa',
            'guest_name' => 'Kwame Antwi',
            // no guest_email
        ]);

        Mail::assertNothingSent();
        $this->assertSame(0, NotificationLog::where('channel', NotificationChannel::Email->value)->count());
    }

    public function test_a_registered_customers_account_email_is_used_when_no_guest_email_applies(): void
    {
        Mail::fake();

        $user = $this->makeCustomer(['email' => 'yaw.asante@example.test']);
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->actingAs($user);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('checkout.store'), [
            'payment_method' => 'momo',
            'momo_number' => '+233200000091',
            'recipient_name' => $user->name,
            'phone' => '+233200000091',
            'delivery_zone_id' => $zone->id,
            'address_line' => '8 High Street, Tarkwa',
        ]);

        Mail::assertSent(OrderEventMail::class, fn (OrderEventMail $mail) => $mail->order->user_id === $user->id);

        $this->assertDatabaseHas('notification_logs', [
            'channel' => NotificationChannel::Email->value,
            'recipient' => 'yaw.asante@example.test',
        ]);
    }
}

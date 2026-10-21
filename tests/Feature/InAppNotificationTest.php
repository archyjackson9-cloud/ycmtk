<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Notifications\NewOrderPlaced;
use App\Notifications\OrderStatusUpdated;
use App\Services\Payments\ManualPaymentService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * In-app (bell) notifications: staff get alerted the moment a customer
 * places an order (NewOrderPlaced, shown in the Filament panel's bell),
 * and a registered customer gets a matching bell notification for every
 * order-status event on their own orders (OrderStatusUpdated, shown on the
 * storefront). Guests have no account to show a bell in.
 */
class InAppNotificationTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function enableManualPayment(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('manual_payment_account_name', 'CY-Market Farms Ltd');
        $settings->set('manual_payment_account_number', '0241234567');
    }

    public function test_placing_an_order_notifies_super_admin_and_inventory_officer_but_not_support_agent(): void
    {
        Notification::fake();
        $this->enableManualPayment();

        $superAdmin = $this->makeSuperAdmin();
        $inventoryOfficer = $this->makeInventoryOfficer();
        $supportAgent = $this->makeCustomer(); // stand-in for "not in scope"
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->carryCookies($this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]));
        $this->post(route('checkout.store'), [
            'recipient_name' => 'Kofi Mensah',
            'phone' => '+233200000089',
            'delivery_zone_id' => $zone->id,
            'address_line' => '1 Farm Road, Tarkwa',
            'guest_name' => 'Kofi Mensah',
            'guest_email' => 'guest@example.test',
        ]);

        $order = Order::firstOrFail();

        Notification::assertSentTo($superAdmin, NewOrderPlaced::class, fn ($n) => $n->order->is($order));
        Notification::assertSentTo($inventoryOfficer, NewOrderPlaced::class);
        Notification::assertNotSentTo($supportAgent, NewOrderPlaced::class);
    }

    public function test_an_inactive_staff_member_is_not_notified(): void
    {
        Notification::fake();
        $this->enableManualPayment();

        $deactivated = $this->makeSuperAdmin(['is_active' => false]);
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->carryCookies($this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]));
        $this->post(route('checkout.store'), [
            'recipient_name' => 'Ama Owusu',
            'phone' => '+233200000088',
            'delivery_zone_id' => $zone->id,
            'address_line' => '2 Farm Road, Tarkwa',
            'guest_name' => 'Ama Owusu',
            'guest_email' => 'guest@example.test',
        ]);

        Notification::assertNotSentTo($deactivated, NewOrderPlaced::class);
    }

    public function test_a_registered_customer_gets_a_bell_notification_for_their_order_status(): void
    {
        $this->enableManualPayment();
        $user = $this->makeCustomer();
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->actingAs($user);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('checkout.store'), [
            'recipient_name' => $user->name,
            'phone' => '+233200000087',
            'delivery_zone_id' => $zone->id,
            'address_line' => '3 Farm Road, Tarkwa',
        ]);

        $order = Order::firstOrFail();
        $this->assertSame(1, $user->notifications()->count(), 'OrderPlaced should have created one bell notification.');
        $this->assertSame(1, $user->unreadNotifications()->count());

        $admin = $this->makeSuperAdmin();
        app(ManualPaymentService::class)->confirm($order->latestPayment(), $admin, approved: true, amountReceived: (float) $order->total);

        // PaymentReceived + OrderConfirmed are both fired on confirmation.
        $this->assertSame(3, $user->notifications()->count());
        $this->assertSame(3, $user->unreadNotifications()->count());

        $latest = $user->notifications()->latest()->first();
        $this->assertSame($order->id, $latest->data['order_id']);
        $this->assertSame(route('orders.show', $order), $latest->data['url']);
    }

    public function test_a_guest_order_creates_no_bell_notification_for_anyone_to_read(): void
    {
        $this->enableManualPayment();
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->carryCookies($this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]));
        $this->post(route('checkout.store'), [
            'recipient_name' => 'Guest Buyer',
            'phone' => '+233200000086',
            'delivery_zone_id' => $zone->id,
            'address_line' => '4 Farm Road, Tarkwa',
            'guest_name' => 'Guest Buyer',
            'guest_email' => 'guest@example.test',
        ]);

        $order = Order::firstOrFail();
        $this->assertNull($order->user_id);
        $this->assertSame(0, \DB::table('notifications')->where('type', OrderStatusUpdated::class)->count());
    }

    public function test_customer_can_open_a_notification_which_marks_it_read_and_redirects_to_the_order(): void
    {
        $this->enableManualPayment();
        $user = $this->makeCustomer();
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->actingAs($user);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('checkout.store'), [
            'recipient_name' => $user->name,
            'phone' => '+233200000085',
            'delivery_zone_id' => $zone->id,
            'address_line' => '5 Farm Road, Tarkwa',
        ]);

        $order = Order::firstOrFail();
        $notification = $user->notifications()->firstOrFail();
        $this->assertNull($notification->read_at);

        $response = $this->get(route('notifications.open', $notification->id));

        $response->assertRedirect(route('orders.show', $order));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_customer_can_mark_all_notifications_read(): void
    {
        $this->enableManualPayment();
        $user = $this->makeCustomer();
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->actingAs($user);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('checkout.store'), [
            'recipient_name' => $user->name,
            'phone' => '+233200000084',
            'delivery_zone_id' => $zone->id,
            'address_line' => '6 Farm Road, Tarkwa',
        ]);

        $this->assertSame(1, $user->unreadNotifications()->count());

        $this->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_a_customer_cannot_open_another_customers_notification(): void
    {
        $this->enableManualPayment();
        $owner = $this->makeCustomer();
        $intruder = $this->makeCustomer();
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->actingAs($owner);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('checkout.store'), [
            'recipient_name' => $owner->name,
            'phone' => '+233200000083',
            'delivery_zone_id' => $zone->id,
            'address_line' => '7 Farm Road, Tarkwa',
        ]);

        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($intruder);
        $this->get(route('notifications.open', $notification->id))->assertNotFound();
    }
}

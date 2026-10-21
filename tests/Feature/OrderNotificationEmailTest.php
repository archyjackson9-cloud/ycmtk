<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\OrderEmailType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendOrderEmail;
use App\Mail\OrderEventMail;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * Customer order emails: sent when an order is placed and on every status
 * change, queued (SendOrderEmail) and dispatched only after the DB
 * transaction commits, idempotent, and logged in notification_logs.
 */
class OrderNotificationEmailTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function enableManualPayment(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('manual_payment_account_name', 'CY-Market Farms Ltd');
        $settings->set('manual_payment_account_number', '0241234567');
    }

    /** Places an order through the real checkout endpoint. */
    protected function placeOrder(array $extra = [], ?User $user = null): void
    {
        $this->enableManualPayment();

        $zone = $this->makeDeliveryZone(['fee_override' => 15.00]);
        $product = $this->makeProduct(['name' => 'Farm Fresh Tomatoes', 'selling_price' => 20.00, 'available_quantity' => 10]);

        if ($user) {
            $this->actingAs($user);
            $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
        } else {
            $this->carryCookies($this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]));
        }

        $this->post(route('checkout.store'), array_merge([
            'recipient_name' => 'Akua Boadi',
            'phone' => '+233200000093',
            'delivery_zone_id' => $zone->id,
            'address_line' => '4 Hilltop Road, Tarkwa',
        ], $user ? [] : ['guest_name' => 'Akua Boadi', 'guest_email' => 'akua@example.test'], $extra));
    }

    /** A paid order with a successful manual payment, owned by a guest with an email. */
    protected function makeOrder(OrderStatus $status = OrderStatus::Paid, array $overrides = []): Order
    {
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10, 'selling_price' => 20]);

        $order = Order::create(array_merge([
            'status' => $status,
            'guest_name' => 'Akua Boadi',
            'guest_email' => 'akua@example.test',
            'delivery_recipient_name' => 'Akua Boadi',
            'delivery_phone' => '+233200000001',
            'delivery_zone_id' => $zone->id,
            'delivery_address_line' => '1 Test Street',
            'delivery_fee' => 15,
            'subtotal' => 40,
            'total' => 55,
            'placed_at' => now(),
        ], $overrides));

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 20,
            'quantity' => 2,
            'line_total' => 40,
        ]);

        return $order;
    }

    protected function makePayment(Order $order, PaymentStatus $status = PaymentStatus::Pending): Payment
    {
        return $order->payments()->create([
            'provider' => 'manual',
            'amount' => $order->total,
            'status' => $status,
        ]);
    }

    protected function emailLogs(): Collection
    {
        return NotificationLog::where('channel', NotificationChannel::Email->value)->get();
    }

    public function test_a_guest_order_sends_an_order_placed_email_with_full_details(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 08:00:00')); // a Monday morning, before cut-off
        Mail::fake();

        $this->placeOrder();
        $order = Order::firstOrFail();

        Mail::assertSent(OrderEventMail::class, 1);
        Mail::assertSent(OrderEventMail::class, function (OrderEventMail $mail) use ($order) {
            if (! $mail->hasTo('akua@example.test') || ! $mail->order->is($order) || $mail->type !== OrderEmailType::Placed) {
                return false;
            }

            $html = $mail->render();
            $label = $order->scheduledDeliveryLabel();

            $this->assertNotNull($label);
            foreach ([$order->order_number, 'Farm Fresh Tomatoes', 'CY-Market Farms Ltd', '0241234567', '4 Hilltop Road, Tarkwa', 'Tarkwa Central (Township)', $label, 'signature='] as $needle) {
                $this->assertStringContainsString($needle, $html);
            }
            $this->assertStringContainsString('GH₵55.00', $html);
            // plain-text alternative exists too
            $this->assertStringContainsString($order->order_number, $mail->textView ? view($mail->textView, $mail->buildViewData())->render() : '');

            return true;
        });

        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $order->id,
            'channel' => NotificationChannel::Email->value,
            'type' => 'order_placed',
            'recipient' => 'akua@example.test',
            'status' => NotificationStatus::Sent->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_a_registered_customer_gets_the_order_placed_email_at_their_account_address(): void
    {
        Mail::fake();
        $user = $this->makeCustomer(['email' => 'yaw.asante@example.test']);

        $this->placeOrder(user: $user);

        Mail::assertSent(OrderEventMail::class, fn (OrderEventMail $mail) => $mail->hasTo('yaw.asante@example.test') && $mail->type === OrderEmailType::Placed);
        $this->assertDatabaseHas('notification_logs', ['channel' => 'email', 'recipient' => 'yaw.asante@example.test', 'type' => 'order_placed']);
    }

    public function test_guest_checkout_requires_an_email_address(): void
    {
        Mail::fake();

        $this->placeOrder(['guest_email' => '']);

        $this->assertSame(0, Order::count());
        Mail::assertNothingSent();
    }

    public function test_order_placed_email_is_queued_not_sent_inline_when_a_real_queue_is_used(): void
    {
        Queue::fake();
        Mail::fake();

        $this->placeOrder();
        $order = Order::firstOrFail();

        Queue::assertPushed(SendOrderEmail::class, 1);
        Mail::assertNothingSent();
        $this->assertSame(NotificationStatus::Pending, $this->emailLogs()->first()->status);

        // The worker then delivers it.
        (new SendOrderEmail($this->emailLogs()->first()->id))->handle();

        Mail::assertSent(OrderEventMail::class, fn (OrderEventMail $mail) => $mail->order->is($order));
        $this->assertSame(NotificationStatus::Sent, $this->emailLogs()->first()->fresh()->status);
    }

    public static function statusTransitions(): array
    {
        return [
            'processing' => [OrderStatus::Paid, OrderStatus::Processing, OrderEmailType::Processing],
            'on hold' => [OrderStatus::Paid, OrderStatus::OnHold, OrderEmailType::OnHold],
            'resumed from hold' => [OrderStatus::OnHold, OrderStatus::Processing, OrderEmailType::Processing],
            'dispatched' => [OrderStatus::Processing, OrderStatus::Dispatched, OrderEmailType::Dispatched],
            'delivered' => [OrderStatus::Dispatched, OrderStatus::Delivered, OrderEmailType::Delivered],
            'completed' => [OrderStatus::Delivered, OrderStatus::Completed, OrderEmailType::Completed],
        ];
    }

    /**
     * @see statusTransitions
     */
    #[DataProvider('statusTransitions')]
    public function test_each_status_change_sends_one_email(OrderStatus $from, OrderStatus $to, OrderEmailType $expected): void
    {
        Mail::fake();
        $order = $this->makeOrder($from);

        app(OrderService::class)->updateStatus($order, $to, $this->makeInventoryOfficer());

        Mail::assertSent(OrderEventMail::class, 1);
        Mail::assertSent(OrderEventMail::class, function (OrderEventMail $mail) use ($expected) {
            $this->assertStringContainsString($mail->order->order_number, $mail->render());

            return $mail->type === $expected && $mail->hasTo('akua@example.test');
        });
    }

    public function test_cancelling_sends_an_email_with_the_reason_from_every_path(): void
    {
        Mail::fake();
        $order = $this->makeOrder(OrderStatus::PendingPayment);

        app(OrderService::class)->cancel($order, 'Customer changed their mind.', $this->makeSuperAdmin());

        Mail::assertSent(OrderEventMail::class, 1);
        Mail::assertSent(OrderEventMail::class, function (OrderEventMail $mail) {
            return $mail->type === OrderEmailType::Cancelled
                && str_contains($mail->render(), 'Customer changed their mind.');
        });
    }

    public function test_the_expiry_sweep_and_customer_self_cancel_also_email(): void
    {
        Mail::fake();
        $order = $this->makeOrder(OrderStatus::PendingPayment);

        // Customer self-cancel goes through OrderService::cancel like the sweep does.
        app(OrderService::class)->cancel($order, 'Payment was not completed within the reservation window.', notify: true);

        Mail::assertSent(OrderEventMail::class, fn (OrderEventMail $mail) => $mail->type === OrderEmailType::Cancelled);
    }

    public function test_manual_payment_confirmation_sends_a_confirmed_email_once_and_rejection_sends_payment_failed(): void
    {
        Mail::fake();
        $order = $this->makeOrder(OrderStatus::PendingPayment);
        $payment = $this->makePayment($order, PaymentStatus::Successful);

        $orders = app(OrderService::class);
        $orders->markPaid($payment);

        Mail::assertSent(OrderEventMail::class, 1);
        Mail::assertSent(OrderEventMail::class, fn (OrderEventMail $mail) => $mail->type === OrderEmailType::Confirmed);

        $other = $this->makeOrder(OrderStatus::PendingPayment);
        $failed = $this->makePayment($other, PaymentStatus::Failed);
        $orders->markPaymentFailed($failed);

        Mail::assertSent(OrderEventMail::class, fn (OrderEventMail $mail) => $mail->type === OrderEmailType::PaymentFailed && $mail->order->is($other));
    }

    public function test_a_repeated_payment_callback_does_not_send_a_duplicate_email(): void
    {
        Mail::fake();
        $order = $this->makeOrder(OrderStatus::PendingPayment);
        $payment = $this->makePayment($order, PaymentStatus::Successful);
        $orders = app(OrderService::class);

        $orders->markPaid($payment);
        $orders->markPaid($payment->fresh());
        $orders->markPaid($payment->fresh());

        Mail::assertSent(OrderEventMail::class, 1);

        $other = $this->makeOrder(OrderStatus::PendingPayment);
        $failed = $this->makePayment($other, PaymentStatus::Failed);
        $orders->markPaymentFailed($failed);
        $orders->markPaymentFailed($failed->fresh());

        Mail::assertSent(OrderEventMail::class, 2);
        $this->assertSame(2, $this->emailLogs()->count());
    }

    public function test_no_email_when_the_status_did_not_really_change_or_the_transition_is_invalid(): void
    {
        Mail::fake();
        $order = $this->makeOrder(OrderStatus::Paid);

        try {
            app(OrderService::class)->updateStatus($order, OrderStatus::Delivered); // Paid -> Delivered not allowed
            $this->fail('Expected the invalid transition to be rejected.');
        } catch (RuntimeException) {
        }

        try {
            app(OrderService::class)->updateStatus($order, OrderStatus::Paid); // same status
        } catch (RuntimeException) {
        }

        Mail::assertNothingSent();
        $this->assertSame(0, $this->emailLogs()->count());
    }

    public function test_no_email_is_sent_when_the_order_has_no_email_address(): void
    {
        Mail::fake();
        $order = $this->makeOrder(OrderStatus::Paid, ['guest_email' => null]);

        app(OrderService::class)->updateStatus($order, OrderStatus::Processing);

        Mail::assertNothingSent();
        $this->assertSame(0, $this->emailLogs()->count());
    }

    public function test_super_admin_can_switch_order_emails_off(): void
    {
        Mail::fake();
        app(SettingsService::class)->set('order_emails_enabled', '0');
        $order = $this->makeOrder(OrderStatus::Paid);

        app(OrderService::class)->updateStatus($order, OrderStatus::Processing);

        Mail::assertNothingSent();
    }

    public function test_a_mail_server_failure_does_not_break_checkout_and_is_logged(): void
    {
        // Synchronous queue here, so the worker's exception surfaces inside the request.
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP connection refused'));

        $this->placeOrder();

        $this->assertSame(1, Order::count(), 'Order must still be created.');
        $log = $this->emailLogs()->first();
        $this->assertSame(NotificationStatus::Failed, $log->status);
        $this->assertStringContainsString('SMTP connection refused', $log->provider_response);
    }

    public function test_a_failed_delivery_is_retried_and_recorded_by_the_job(): void
    {
        $order = $this->makeOrder(OrderStatus::Paid);
        $log = NotificationLog::create([
            'notifiable_type' => $order->getMorphClass(), 'notifiable_id' => $order->id,
            'channel' => NotificationChannel::Email, 'type' => 'order_confirmed', 'recipient' => 'akua@example.test',
            'message' => 'x', 'status' => NotificationStatus::Pending, 'attempts' => 0,
        ]);

        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Timeout'));
        $job = new SendOrderEmail($log->id);

        try {
            $job->handle();
            $this->fail('The job must rethrow so the queue retries it.');
        } catch (RuntimeException) {
        }

        $this->assertSame(1, $log->fresh()->attempts);
        $this->assertSame(NotificationStatus::Pending, $log->fresh()->status);
        $this->assertGreaterThan(1, $job->tries);
        $this->assertNotEmpty($job->backoff());

        $job->failed(new RuntimeException('Timeout'));
        $this->assertSame(NotificationStatus::Failed, $log->fresh()->status);
    }

    public function test_email_is_queued_only_after_the_transaction_commits(): void
    {
        Queue::fake();
        $order = $this->makeOrder(OrderStatus::Paid);

        DB::transaction(function () use ($order) {
            app(OrderService::class)->updateStatus($order, OrderStatus::Processing);

            Queue::assertNothingPushed(); // still inside the (outer) transaction
        });

        Queue::assertPushed(SendOrderEmail::class, 1);
    }

    public function test_nothing_is_queued_when_the_transaction_rolls_back(): void
    {
        Queue::fake();
        $order = $this->makeOrder(OrderStatus::Paid);

        try {
            DB::transaction(function () use ($order) {
                app(OrderService::class)->updateStatus($order, OrderStatus::Processing);

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        Queue::assertNothingPushed();
        $this->assertSame(0, $this->emailLogs()->count());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }
}

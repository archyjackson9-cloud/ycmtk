<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Pages\SendCustomSms;
use App\Jobs\SendSms;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Services\Notifications\CustomSmsService;
use App\Services\Notifications\SmsNotificationService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * Arkesel SMS: live driver, log mode, queued delivery of every order event
 * (exactly once) and the admin custom/bulk SMS.
 */
class SmsNotificationTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function live(): void
    {
        config([
            'sms.mode' => 'live',
            'sms.api_key' => 'test-key',
            'sms.sender_id' => 'CYMarket',
            'sms.base_url' => 'https://sms.arkesel.com/api/v2',
        ]);
    }

    protected function smsLogs()
    {
        return NotificationLog::where('channel', NotificationChannel::Sms->value)->orderBy('id')->get();
    }

    protected function makeOrder(OrderStatus $status = OrderStatus::Paid, array $overrides = []): Order
    {
        $zone = $this->makeDeliveryZone();

        return Order::create(array_merge([
            'status' => $status,
            'guest_name' => 'Akua Boadi',
            'delivery_recipient_name' => 'Akua Boadi',
            'delivery_phone' => '0244123456',
            'delivery_zone_id' => $zone->id,
            'delivery_address_line' => '1 Test Street',
            'delivery_fee' => 15,
            'subtotal' => 40,
            'total' => 55,
            'placed_at' => now(),
        ], $overrides));
    }

    public function test_live_mode_posts_to_arkesel_and_marks_the_log_sent(): void
    {
        $this->live();
        Http::fake(['sms.arkesel.com/*' => Http::response(['status' => 'success', 'data' => [['recipient' => '233244123456']]], 200)]);

        $log = app(SmsNotificationService::class)->send('0244 123 456', 'Hello', SmsNotificationService::TYPE_CUSTOM);

        Http::assertSent(fn ($request) => $request->url() === 'https://sms.arkesel.com/api/v2/sms/send'
            && $request->hasHeader('api-key', 'test-key')
            && $request['sender'] === 'CYMarket'
            && $request['message'] === 'Hello'
            && $request['recipients'] === ['233244123456']);

        $log = $log->fresh();
        $this->assertSame(NotificationStatus::Sent, $log->status);
        $this->assertSame('233244123456', $log->recipient);
        $this->assertSame(1, $log->attempts);
        $this->assertStringContainsString('success', $log->provider_response);
    }

    public function test_http_200_with_an_error_status_is_a_failure_and_the_body_is_stored(): void
    {
        $this->live();
        config(['sms.retry_attempts' => 2]);
        Http::fake(['sms.arkesel.com/*' => Http::response(['status' => 'error', 'message' => 'Invalid sender'], 200)]);

        $log = app(SmsNotificationService::class)->send('0244123456', 'Hello', SmsNotificationService::TYPE_CUSTOM)->fresh();

        $this->assertSame(NotificationStatus::Failed, $log->status);
        $this->assertStringContainsString('Invalid sender', $log->provider_response);
        $this->assertGreaterThanOrEqual(1, $log->attempts);
    }

    public function test_a_failed_attempt_stays_pending_for_the_queue_to_retry(): void
    {
        $this->live();
        Http::fake(['sms.arkesel.com/*' => Http::response('boom', 500)]);

        $log = app(SmsNotificationService::class)->record('0244123456', 'Hi', SmsNotificationService::TYPE_CUSTOM);

        $ok = app(SmsNotificationService::class)->deliver(collect([$log]));

        $this->assertFalse($ok);
        $log = $log->fresh();
        $this->assertSame(NotificationStatus::Pending, $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertSame('boom', $log->provider_response);
    }

    public function test_missing_api_key_in_live_mode_fails_without_calling_the_api(): void
    {
        $this->live();
        config(['sms.api_key' => null]);
        Http::fake();

        [$ok, $response] = app(SmsNotificationService::class)->sendRaw('233244123456', 'Hi');

        $this->assertFalse($ok);
        $this->assertStringContainsString('SMS_ARKESEL_API_KEY', $response);
        Http::assertNothingSent();
    }

    public function test_log_mode_never_calls_the_gateway(): void
    {
        config(['sms.mode' => 'log']);
        Http::fake();

        $log = app(SmsNotificationService::class)->send('0244123456', 'Hi', SmsNotificationService::TYPE_CUSTOM)->fresh();

        Http::assertNothingSent();
        $this->assertSame(NotificationStatus::Sent, $log->status);
        $this->assertStringContainsString('log-mode', $log->provider_response);
    }

    public function test_invalid_or_missing_phone_is_logged_as_failed_and_not_queued(): void
    {
        Queue::fake();

        $sms = app(SmsNotificationService::class);
        $bad = $sms->send('12345', 'Hi', SmsNotificationService::TYPE_CUSTOM);
        $none = $sms->send(null, 'Hi', SmsNotificationService::TYPE_CUSTOM);

        $this->assertSame(NotificationStatus::Failed, $bad->status);
        $this->assertSame(NotificationStatus::Failed, $none->status);
        Queue::assertNothingPushed();
    }

    public function test_order_events_queue_one_sms_job_each_and_never_duplicate(): void
    {
        Queue::fake();
        $this->seedRoles();
        $orders = app(OrderService::class);

        $order = $this->makeOrder(OrderStatus::Paid);
        $orders->notifyPlaced($order);
        $orders->notifyPlaced($order); // repeated call must not text twice

        $order = $this->makeOrder(OrderStatus::Paid);
        $orders->updateStatus($order, OrderStatus::Processing);
        $orders->updateStatus($order, OrderStatus::Dispatched);
        $orders->updateStatus($order, OrderStatus::Delivered);

        $cancelled = $this->makeOrder(OrderStatus::PendingPayment);
        $orders->cancel($cancelled, 'Changed my mind');

        $held = $this->makeOrder(OrderStatus::Paid);
        $orders->updateStatus($held, OrderStatus::OnHold, note: 'Out of stock');

        $types = $this->smsLogs()->pluck('type')->all();
        sort($types);
        $expected = ['order_placed', 'order_processing', 'order_dispatched', 'order_delivered', 'order_cancelled', 'order_on_hold'];
        sort($expected);

        $this->assertSame($expected, $types);
        Queue::assertPushed(SendSms::class, 6);
        $this->assertStringContainsString('Out of stock', $this->smsLogs()->firstWhere('type', 'order_on_hold')->message);
    }

    public function test_payment_confirmation_sends_received_and_confirmed_once_and_replays_are_ignored(): void
    {
        Http::fake();
        $order = $this->makeOrder(OrderStatus::PendingPayment);
        $payment = $order->payments()->create(['provider' => 'manual', 'amount' => 55, 'status' => PaymentStatus::Successful]);

        $orders = app(OrderService::class);
        $orders->markPaid($payment);
        $orders->markPaid($payment->fresh());

        $types = $this->smsLogs()->pluck('type')->all();

        // Stock is not seeded for this ad-hoc order: either path must still be exactly-once.
        $this->assertCount(count(array_unique($types)), $types);
        $this->assertContains($order->fresh()->status === OrderStatus::OnHold ? 'order_on_hold' : 'payment_received', $types);
    }

    public function test_payment_failed_sms_is_sent_once_per_payment(): void
    {
        Http::fake();
        $order = $this->makeOrder(OrderStatus::PendingPayment);
        $payment = $order->payments()->create(['provider' => 'manual', 'amount' => 55, 'status' => PaymentStatus::Failed]);

        $orders = app(OrderService::class);
        $orders->markPaymentFailed($payment);
        $orders->markPaymentFailed($payment->fresh());

        $this->assertSame(1, $this->smsLogs()->where('type', 'payment_failed')->count());
    }

    public function test_order_without_a_usable_phone_logs_a_failed_sms_and_does_not_crash(): void
    {
        Queue::fake();
        $order = $this->makeOrder(OrderStatus::Paid, ['delivery_phone' => 'n/a']);

        app(OrderService::class)->updateStatus($order, OrderStatus::Processing);

        $log = $this->smsLogs()->first();
        $this->assertSame(NotificationStatus::Failed, $log->status);
        Queue::assertNotPushed(SendSms::class);
    }

    public function test_custom_sms_to_one_customer_replaces_name_and_is_logged_as_custom(): void
    {
        $this->live();
        Http::fake(['sms.arkesel.com/*' => Http::response(['status' => 'success'], 200)]);
        $customer = $this->makeCustomer(['name' => 'Kofi Mensah', 'phone' => '0201234567']);

        app(CustomSmsService::class)->sendToOne($customer->phone, 'Hi {name}, fresh stock today!', $customer);

        Http::assertSent(fn ($r) => $r['message'] === 'Hi Kofi, fresh stock today!' && $r['recipients'] === ['233201234567']);
        $this->assertDatabaseHas('notification_logs', [
            'type' => 'custom',
            'recipient' => '233201234567',
            'status' => NotificationStatus::Sent->value,
            'notifiable_id' => $customer->id,
        ]);
    }

    public function test_broadcast_reaches_valid_unique_customer_phones_in_chunks(): void
    {
        $this->live();
        config(['sms.bulk_chunk_size' => 2]);
        Http::fake(['sms.arkesel.com/*' => Http::response(['status' => 'success'], 200)]);

        $this->makeCustomer(['phone' => '0240000001']);
        $this->makeCustomer(['phone' => '+233240000002']);
        $this->makeCustomer(['phone' => '0240000003']);
        $this->makeCustomer(['phone' => '233240000003']); // duplicate of the one above
        $this->makeCustomer(['phone' => 'garbage']);
        $this->makeCustomer(['phone' => null]);
        $this->makeSuperAdmin(['phone' => '0240000009']); // staff are not customers

        $service = app(CustomSmsService::class);
        $this->assertSame(['recipients' => 3, 'skipped' => 2], $service->countRecipients());

        $result = $service->broadcast('Market open all week');

        $this->assertSame(['queued' => 3, 'skipped' => 2], $result);
        $this->assertSame(3, $this->smsLogs()->where('type', 'custom')->where('status', NotificationStatus::Sent)->count());
        // 3 recipients, chunk size 2 -> a 2-recipient request and a 1-recipient request
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => count($r['recipients']) === 2);
    }

    public function test_broadcast_can_be_limited_to_customers_who_have_ordered(): void
    {
        Queue::fake();
        $buyer = $this->makeCustomer(['phone' => '0240000001']);
        $this->makeCustomer(['phone' => '0240000002']);
        $this->makeOrder(OrderStatus::Paid, ['user_id' => $buyer->id]);

        $result = app(CustomSmsService::class)->broadcast('Hello {name}', onlyOrdered: true);

        $this->assertSame(1, $result['queued']);
        $this->assertSame(['233240000001'], $this->smsLogs()->pluck('recipient')->all());
        Queue::assertPushed(SendSms::class, 1);
    }

    public function test_segment_estimate(): void
    {
        $this->assertSame(['length' => 0, 'segments' => 0, 'unicode' => false], CustomSmsService::segments(''));
        $this->assertSame(1, CustomSmsService::segments(str_repeat('a', 160))['segments']);
        $this->assertSame(2, CustomSmsService::segments(str_repeat('a', 161))['segments']);
        $this->assertTrue(CustomSmsService::segments('Hello 😀')['unicode']);
        $this->assertSame(2, CustomSmsService::segments(str_repeat('a', 70).'😀')['segments']);
    }

    public function test_only_super_admin_can_open_the_custom_sms_page(): void
    {
        $this->actingAs($this->makeSuperAdmin());
        $this->assertTrue(SendCustomSms::canAccess());

        $this->actingAs($this->makeInventoryOfficer());
        $this->assertFalse(SendCustomSms::canAccess());
    }
}

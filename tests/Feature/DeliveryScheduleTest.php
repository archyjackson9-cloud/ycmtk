<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\DeliveryScheduleService;
use App\Services\SettingsService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

class DeliveryScheduleTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function at(string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime, 'Africa/Accra');
    }

    protected function deliveryFor(string $datetime): string
    {
        return app(DeliveryScheduleService::class)->deliveryAt($this->at($datetime))->format('Y-m-d H:i');
    }

    public function test_weekday_before_cutoff_is_delivered_today_at_4pm(): void
    {
        // 2026-10-21 is a Wednesday.
        $schedule = app(DeliveryScheduleService::class)->scheduleFor($this->at('2026-10-21 09:59:59'));

        $this->assertFalse($schedule['cutoff_passed']);
        $this->assertSame('2026-10-21 16:00', $schedule['delivery_at']->format('Y-m-d H:i'));
    }

    public function test_weekday_after_cutoff_rolls_to_next_day_4pm(): void
    {
        $schedule = app(DeliveryScheduleService::class)->scheduleFor($this->at('2026-10-21 10:30'));

        $this->assertTrue($schedule['cutoff_passed']);
        $this->assertSame('2026-10-22 16:00', $schedule['delivery_at']->format('Y-m-d H:i'));
    }

    public function test_exactly_at_cutoff_is_late(): void
    {
        $this->assertTrue(app(DeliveryScheduleService::class)->cutoffPassed($this->at('2026-10-21 10:00:00')));
        $this->assertSame('2026-10-22 16:00', $this->deliveryFor('2026-10-21 10:00:00'));
        $this->assertSame('2026-10-21 16:00', $this->deliveryFor('2026-10-21 09:59:59'));
    }

    public function test_friday_after_cutoff_goes_to_saturday_11am(): void
    {
        // 2026-10-23 is a Friday.
        $this->assertSame('2026-10-24 11:00', $this->deliveryFor('2026-10-23 10:30'));
        $this->assertSame('2026-10-23 16:00', $this->deliveryFor('2026-10-23 08:00'));
    }

    public function test_saturday_slots(): void
    {
        $this->assertSame('2026-10-24 11:00', $this->deliveryFor('2026-10-24 09:00'));
        $this->assertSame('2026-10-25 11:00', $this->deliveryFor('2026-10-24 10:00:00'));
    }

    public function test_sunday_after_cutoff_goes_to_monday_4pm(): void
    {
        $this->assertSame('2026-10-25 11:00', $this->deliveryFor('2026-10-25 09:00'));
        $this->assertSame('2026-10-26 16:00', $this->deliveryFor('2026-10-25 10:30'));
    }

    public function test_labels(): void
    {
        $service = app(DeliveryScheduleService::class);

        $this->assertSame('Order before 10:00 AM for delivery today at 4:00 PM', $service->ruleLabel($this->at('2026-10-21 08:00')));
        $this->assertSame('Order before 10:00 AM for delivery today at 11:00 AM', $service->ruleLabel($this->at('2026-10-24 08:00')));
        $this->assertSame(
            "Today's 10:00 AM cut-off has passed. Orders placed now are delivered tomorrow at 11:00 AM.",
            $service->noticeLabel($this->at('2026-10-23 10:30'))
        );
    }

    public function test_uses_the_app_timezone_not_the_input_timezone(): void
    {
        $this->assertSame('Africa/Accra', config('app.timezone'));

        // Lagos is UTC+1, so
        // 10:30 Lagos == 09:30 Accra, i.e. still before the Accra cut-off.
        $lagos = CarbonImmutable::parse('2026-10-21 10:30', 'Africa/Lagos');
        $this->assertFalse(app(DeliveryScheduleService::class)->cutoffPassed($lagos));

        // 10:30 in Bogota (UTC-5) is 15:30 Accra: late.
        $ny = CarbonImmutable::parse('2026-10-21 10:30', 'America/Bogota');
        $this->assertTrue(app(DeliveryScheduleService::class)->cutoffPassed($ny));

        config(['app.timezone' => 'Asia/Tokyo']);
        $this->assertSame('2026-10-21 16:00', app(DeliveryScheduleService::class)->deliveryAt(CarbonImmutable::parse('2026-10-21 00:30', 'UTC'))->format('Y-m-d H:i'));
    }

    public function test_times_are_driven_by_settings(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('delivery_weekday_cutoff', '12:00');
        $settings->set('delivery_weekday_time', '17:30:00');

        $this->assertSame('2026-10-21 17:30', $this->deliveryFor('2026-10-21 11:00'));
        $this->assertSame('2026-10-22 17:30', $this->deliveryFor('2026-10-21 12:00'));
    }

    public function test_checkout_stores_the_scheduled_delivery_time(): void
    {
        // Friday 10:30 -> Saturday 11:00.
        Carbon::setTestNow(Carbon::parse('2026-10-23 10:30', 'Africa/Accra'));

        $settings = app(SettingsService::class);
        $settings->set('manual_payment_account_name', 'CY-Market Farms Ltd');
        $settings->set('manual_payment_account_number', '0241234567');

        $zone = $this->makeDeliveryZone(['fee_override' => 15.00]);
        $product = $this->makeProduct(['selling_price' => 20.00, 'available_quantity' => 10]);

        $this->carryCookies($this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]));

        $this->post(route('checkout.store'), [
            'recipient_name' => 'Kwesi Darko',
            'phone' => '+233200000095',
            'delivery_zone_id' => $zone->id,
            'address_line' => '7 Mine Road, Tarkwa',
            'guest_name' => 'Kwesi Darko',
        ]);

        $order = Order::firstOrFail();

        $this->assertSame('2026-10-24 11:00', $order->scheduled_delivery_at->setTimezone('Africa/Accra')->format('Y-m-d H:i'));
        $this->assertSame('Saturday 24 Oct, 11:00 AM', $order->scheduledDeliveryLabel());

        Carbon::setTestNow();
    }
}

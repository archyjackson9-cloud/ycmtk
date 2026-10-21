<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Same-day delivery schedule: each day has an order cut-off and a delivery
 * time (Mon-Fri and Sat-Sun are configured separately in Admin > Settings,
 * see SettingsService). An order placed BEFORE the day's cut-off is
 * delivered that day; an order placed AT OR AFTER the cut-off (the cut-off
 * instant itself is "late") rolls to the next day's slot.
 *
 * All calculations run in the app timezone (config('app.timezone')).
 */
class DeliveryScheduleService
{
    public function __construct(protected SettingsService $settings) {}

    /**
     * @return array{cutoff_passed: bool, cutoff_at: CarbonImmutable, delivery_at: CarbonImmutable}
     */
    public function scheduleFor(?CarbonInterface $at = null): array
    {
        $at = $this->localise($at);

        $cutoffAt = $this->atTime($at, $this->cutoffFor($at));
        $passed = $at->greaterThanOrEqualTo($cutoffAt);

        $day = $passed ? $at->addDay() : $at;

        return [
            'cutoff_passed' => $passed,
            'cutoff_at' => $cutoffAt,
            'delivery_at' => $this->atTime($day, $this->deliveryFor($day)),
        ];
    }

    public function deliveryAt(?CarbonInterface $at = null): CarbonImmutable
    {
        return $this->scheduleFor($at)['delivery_at'];
    }

    public function cutoffPassed(?CarbonInterface $at = null): bool
    {
        return $this->scheduleFor($at)['cutoff_passed'];
    }

    /**
     * Generic rule line for the given moment's day, e.g.
     * "Order before 10:00 AM for delivery today at 4:00 PM".
     */
    public function ruleLabel(?CarbonInterface $at = null): string
    {
        $at = $this->localise($at);

        return 'Order before '.$this->time($this->cutoffFor($at)).' for delivery today at '.$this->time($this->deliveryFor($at));
    }

    /**
     * Customer-facing notice for the given moment: the rule while the
     * cut-off is open, or where the order will land once it has passed.
     */
    public function noticeLabel(?CarbonInterface $at = null): string
    {
        $schedule = $this->scheduleFor($at);

        if (! $schedule['cutoff_passed']) {
            return $this->ruleLabel($at);
        }

        return "Today's ".$schedule['cutoff_at']->format('g:i A').' cut-off has passed. Orders placed now are delivered '
            .$this->deliveryLabel($schedule['delivery_at'], $at).'.';
    }

    /**
     * "today at 4:00 PM", "tomorrow at 11:00 AM", otherwise "Monday 26 Oct at 4:00 PM".
     */
    public function deliveryLabel(CarbonInterface $deliveryAt, ?CarbonInterface $now = null): string
    {
        $now = $this->localise($now);
        $deliveryAt = $this->localise($deliveryAt);

        $day = match (true) {
            $deliveryAt->isSameDay($now) => 'today',
            $deliveryAt->isSameDay($now->addDay()) => 'tomorrow',
            default => $deliveryAt->format('l j M'),
        };

        return $day.' at '.$deliveryAt->format('g:i A');
    }

    /**
     * Absolute label for stored orders, e.g. "Saturday 24 Oct, 11:00 AM".
     */
    public function formatScheduled(CarbonInterface $deliveryAt): string
    {
        return $this->localise($deliveryAt)->format('l j M, g:i A');
    }

    protected function isWeekend(CarbonInterface $day): bool
    {
        return $day->isSaturday() || $day->isSunday();
    }

    protected function cutoffFor(CarbonInterface $day): string
    {
        return $this->isWeekend($day) ? $this->settings->deliveryWeekendCutoff() : $this->settings->deliveryWeekdayCutoff();
    }

    protected function deliveryFor(CarbonInterface $day): string
    {
        return $this->isWeekend($day) ? $this->settings->deliveryWeekendTime() : $this->settings->deliveryWeekdayTime();
    }

    protected function atTime(CarbonImmutable $day, string $hhmm): CarbonImmutable
    {
        [$h, $m] = array_pad(explode(':', $hhmm), 2, 0);

        return $day->setTime((int) $h, (int) $m, 0);
    }

    protected function time(string $hhmm): string
    {
        return $this->atTime(CarbonImmutable::now($this->timezone()), $hhmm)->format('g:i A');
    }

    protected function localise(?CarbonInterface $at): CarbonImmutable
    {
        return ($at ? CarbonImmutable::instance($at) : CarbonImmutable::now())->setTimezone($this->timezone());
    }

    protected function timezone(): string
    {
        return config('app.timezone');
    }
}

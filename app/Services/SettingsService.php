<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Super-Admin-configurable settings, cached for the request lifecycle
 * (TOR §6.10 RBAC - "settings (delivery fee, SMS templates)").
 */
class SettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever('setting:'.$key, function () use ($key, $default) {
            $value = Setting::where('key', $key)->value('value');

            return $value ?? $default;
        });
    }

    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget('setting:'.$key);
    }

    public function deliveryFee(): float
    {
        return (float) $this->get('delivery_fee', config('cymarket.default_delivery_fee'));
    }

    public function pilotZoneName(): string
    {
        return (string) $this->get('pilot_zone_name', config('cymarket.pilot_zone_name'));
    }

    public function stockReservationMinutes(): int
    {
        return (int) $this->get('stock_reservation_minutes', config('cymarket.stock_reservation_minutes'));
    }

    public function abandonedCartHours(): int
    {
        return (int) $this->get('abandoned_cart_hours', config('cymarket.abandoned_cart_hours'));
    }

    public function orderEscalationHours(): int
    {
        return (int) $this->get('order_escalation_hours', config('cymarket.order_escalation_hours'));
    }

    /**
     * SMS template with {placeholders} for an order event. Falls back to a
     * sensible default so notifications work before Super Admin customises
     * them from Admin > Settings.
     */
    public function smsTemplate(string $eventKey, string $default): string
    {
        return (string) $this->get('sms_template_'.$eventKey, $default);
    }

    public function currencySymbol(): string
    {
        return config('cymarket.currency_symbol');
    }
}

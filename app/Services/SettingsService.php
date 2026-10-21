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

    /**
     * Master switch for customer order emails (Admin > Settings). On by
     * default; the stored value may arrive as "0"/"1"/bool.
     */
    public function orderEmailsEnabled(): bool
    {
        return filter_var($this->get('order_emails_enabled', true), FILTER_VALIDATE_BOOLEAN);
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
     * How long a "pay by transfer" order holds its stock reservation while
     * waiting for a staff member to confirm the payment manually - much
     * longer than the MoMo window since a human has to notice it land.
     */
    public function manualPaymentReservationHours(): int
    {
        return (int) $this->get('manual_payment_reservation_hours', config('cymarket.manual_payment_reservation_hours'));
    }

    public function manualPaymentAccountName(): string
    {
        return (string) $this->get('manual_payment_account_name', '');
    }

    public function manualPaymentAccountNumber(): string
    {
        return (string) $this->get('manual_payment_account_number', '');
    }

    public function manualPaymentNetwork(): string
    {
        return (string) $this->get('manual_payment_network', 'MTN Mobile Money');
    }

    public function manualPaymentInstructions(): string
    {
        return (string) $this->get(
            'manual_payment_instructions',
            'Send the exact total to the account below using your order number as the reference, then come back here - no need to upload anything, but a screenshot helps us confirm faster.'
        );
    }

    /**
     * Whether a Super Admin has actually filled in the pay-by-transfer
     * account details yet - the storefront hides the option until they
     * have, rather than sending customers to a blank instructions page.
     */
    public function manualPaymentConfigured(): bool
    {
        return filled($this->manualPaymentAccountName()) && filled($this->manualPaymentAccountNumber());
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

    /**
     * Delivery schedule (see DeliveryScheduleService): "HH:MM" 24-hour
     * cut-off and delivery times, separately for Mon-Fri and Sat-Sun.
     */
    public function deliveryWeekdayCutoff(): string
    {
        return $this->time('delivery_weekday_cutoff', '10:00');
    }

    public function deliveryWeekdayTime(): string
    {
        return $this->time('delivery_weekday_time', '16:00');
    }

    public function deliveryWeekendCutoff(): string
    {
        return $this->time('delivery_weekend_cutoff', '10:00');
    }

    public function deliveryWeekendTime(): string
    {
        return $this->time('delivery_weekend_time', '11:00');
    }

    /**
     * Normalises a stored time ("10:00", "10:00:00") to "HH:MM", falling
     * back to the default if the stored value is missing or malformed.
     */
    protected function time(string $key, string $default): string
    {
        $value = (string) $this->get($key, $default);

        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $value, $m)) {
            return $default;
        }

        return sprintf('%02d:%s', $m[1], $m[2]);
    }

    public function currencySymbol(): string
    {
        return config('cymarket.currency_symbol');
    }
}

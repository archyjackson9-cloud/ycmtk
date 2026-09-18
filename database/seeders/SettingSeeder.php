<?php

namespace Database\Seeders;

use App\Services\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Seeds the Super-Admin-configurable settings (TOR §6.10, §3.1) with the
 * same values config/cymarket.php already defaults to, so the Filament
 * Settings page (app/Filament/Pages/Settings.php) has real rows to edit
 * from the very first login instead of relying on env fallbacks.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(SettingsService::class);

        $settings->set('delivery_fee', (float) config('cymarket.default_delivery_fee'), 'general');
        $settings->set('pilot_zone_name', config('cymarket.pilot_zone_name'), 'general');
        $settings->set('stock_reservation_minutes', config('cymarket.stock_reservation_minutes'), 'general');
        $settings->set('abandoned_cart_hours', config('cymarket.abandoned_cart_hours'), 'general');
        $settings->set('order_escalation_hours', config('cymarket.order_escalation_hours'), 'general');
    }
}

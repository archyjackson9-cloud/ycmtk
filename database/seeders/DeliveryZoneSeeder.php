<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

/**
 * Tarkwa pilot coverage zones (TOR §3.1 "Fixed, Super-Admin-configurable
 * delivery fee within the Tarkwa pilot coverage zone"). fee_override is
 * left null for zones that should simply use the global delivery fee from
 * Settings, and set for zones the pilot team has priced differently.
 */
class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['name' => 'Tarkwa Central (Township)', 'fee_override' => 10.00],
            ['name' => 'Tarkwa-Nsuaem Municipal', 'fee_override' => null],
            ['name' => 'Aboso', 'fee_override' => null],
            ['name' => 'Huni Valley', 'fee_override' => 20.00],
            ['name' => 'Bogoso', 'fee_override' => 25.00],
            ['name' => 'Prestea', 'fee_override' => 25.00],
            ['name' => 'Damang', 'fee_override' => 30.00],
        ];

        foreach ($zones as $zone) {
            DeliveryZone::updateOrCreate(
                ['name' => $zone['name']],
                ['fee_override' => $zone['fee_override'], 'is_active' => true]
            );
        }
    }
}

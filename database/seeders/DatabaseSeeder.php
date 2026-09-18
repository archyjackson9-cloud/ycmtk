<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orchestrates the full pilot seed set (TOR - "seeders for initial
 * testing, especially products"). Order matters: roles before users,
 * delivery zones before users/orders (addresses reference zones),
 * categories before products, and everything before orders (which
 * reference customers, zones and products).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DeliveryZoneSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            SettingSeeder::class,
            OrderSeeder::class,
        ]);
    }
}

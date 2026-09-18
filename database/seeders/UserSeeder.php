<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Staff and sample customer accounts (TOR §4 Stakeholders & User Classes).
 * Every staff account uses the password "password" - this is sandbox/pilot
 * seed data only, and SETUP.md tells the user to change it before any real
 * deployment.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = config('cymarket.roles');

        // Every seeded account (staff and customer) shares this sandbox
        // password so SETUP.md only needs to document one credential.
        $defaultPassword = 'password';

        // --- Super Admin -----------------------------------------------
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@cymarket.test'],
            [
                'name' => 'CY-Market Admin',
                'phone' => '+233200000001',
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ]
        );
        $superAdmin->assignRole($roles['super_admin']);

        // --- Inventory Officers ------------------------------------------
        $inventoryOfficers = [
            ['name' => 'Akosua Mensah', 'email' => 'inventory1@cymarket.test', 'phone' => '+233200000002'],
            ['name' => 'Kwabena Owusu', 'email' => 'inventory2@cymarket.test', 'phone' => '+233200000003'],
        ];

        foreach ($inventoryOfficers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'phone' => $data['phone'], 'is_active' => true, 'email_verified_at' => now(), 'password' => $defaultPassword]
            );
            $user->assignRole($roles['inventory_officer']);
        }

        // --- Marketing Admin & Support Agent (Phase 2 roles, scaffolded) --
        $marketingAdmin = User::updateOrCreate(
            ['email' => 'marketing@cymarket.test'],
            ['name' => 'Efua Baidoo', 'phone' => '+233200000004', 'is_active' => true, 'email_verified_at' => now(), 'password' => $defaultPassword]
        );
        $marketingAdmin->assignRole($roles['marketing_admin']);

        $supportAgent = User::updateOrCreate(
            ['email' => 'support@cymarket.test'],
            ['name' => 'Yaw Boateng', 'phone' => '+233200000005', 'is_active' => true, 'email_verified_at' => now(), 'password' => $defaultPassword]
        );
        $supportAgent->assignRole($roles['support_agent']);

        // --- Sample customers --------------------------------------------
        $zoneIds = DeliveryZone::pluck('id');

        $customerNames = [
            ['Ama Serwaa', 'ama.serwaa@example.test'],
            ['Kofi Adjei', 'kofi.adjei@example.test'],
            ['Abena Frimpong', 'abena.frimpong@example.test'],
            ['Yaw Asante', 'yaw.asante@example.test'],
            ['Adwoa Nyarko', 'adwoa.nyarko@example.test'],
            ['Kwesi Darko', 'kwesi.darko@example.test'],
            ['Akua Boadi', 'akua.boadi@example.test'],
            ['Kwame Antwi', 'kwame.antwi@example.test'],
        ];

        foreach ($customerNames as $i => [$name, $email]) {
            $customer = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => '+233201000'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password' => $defaultPassword,
                ]
            );
            $customer->assignRole($roles['customer']);

            if ($customer->addresses()->count() === 0 && $zoneIds->isNotEmpty()) {
                Address::create([
                    'user_id' => $customer->id,
                    'label' => 'Home',
                    'recipient_name' => $name,
                    'phone' => $customer->phone,
                    'delivery_zone_id' => $zoneIds->random(),
                    'address_line' => fake()->numberBetween(1, 99).' '.fake()->streetName().', Tarkwa',
                    'landmark' => fake()->randomElement(['Near the Tarkwa lorry station', 'Opposite UMaT campus', 'Behind Tarkwa Municipal Hospital', 'Near St. John\'s Church', null]),
                    'is_default' => true,
                ]);
            }
        }
    }
}

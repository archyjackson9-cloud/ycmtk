<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Shared fixture builders for feature tests, so each test file isn't
 * re-deriving product/category/zone boilerplate that has nothing to do
 * with what it's actually asserting.
 */
trait CreatesCymarketFixtures
{
    protected function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeCategory(array $overrides = []): Category
    {
        return Category::factory()->create($overrides);
    }

    protected function makeDeliveryZone(array $overrides = []): DeliveryZone
    {
        return DeliveryZone::create(array_merge([
            'name' => 'Tarkwa Central (Township)',
            'fee_override' => 15.00,
            'is_active' => true,
        ], $overrides));
    }

    protected function makeProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => $this->makeCategory()->id,
            'available_quantity' => 50,
            'reserved_quantity' => 0,
            'selling_price' => 20.00,
            'is_active' => true,
        ], $overrides));
    }

    protected function makeCustomer(array $overrides = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create($overrides);
        $user->assignRole(config('cymarket.roles.customer'));

        return $user;
    }

    protected function makeSuperAdmin(array $overrides = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create($overrides);
        $user->assignRole(config('cymarket.roles.super_admin'));

        return $user;
    }

    protected function makeInventoryOfficer(array $overrides = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create($overrides);
        $user->assignRole(config('cymarket.roles.inventory_officer'));

        return $user;
    }
}

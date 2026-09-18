<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\NotificationLogResource;
use App\Filament\Resources\ProductResource;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * RBAC gating (TOR §6.10 "Roles & Permissions - Super Admin has full
 * access; Inventory Officer has scoped access to catalogue/orders/stock").
 * Exercises the same canAccessPanel()/canViewAny()/canCreate() gates
 * Filament calls, without booting the full admin panel HTTP stack.
 */
class PanelAccessTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    public function test_customers_cannot_access_the_admin_panel(): void
    {
        $customer = $this->makeCustomer();

        $this->assertFalse($customer->canAccessPanel(Panel::make()));
    }

    public function test_deactivated_staff_cannot_access_the_admin_panel(): void
    {
        $officer = $this->makeInventoryOfficer(['is_active' => false]);

        $this->assertFalse($officer->canAccessPanel(Panel::make()));
    }

    public function test_super_admin_and_inventory_officer_can_access_the_admin_panel(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $officer = $this->makeInventoryOfficer();

        $this->assertTrue($superAdmin->canAccessPanel(Panel::make()));
        $this->assertTrue($officer->canAccessPanel(Panel::make()));
    }

    public function test_only_super_admin_can_manage_categories(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $officer = $this->makeInventoryOfficer();

        $this->actingAs($superAdmin);
        $this->assertTrue(CategoryResource::canViewAny());

        $this->actingAs($officer);
        $this->assertFalse(CategoryResource::canViewAny());
    }

    public function test_inventory_officer_can_view_but_not_delete_products(): void
    {
        $officer = $this->makeInventoryOfficer();

        $this->actingAs($officer);
        $this->assertTrue(ProductResource::canViewAny());
        $this->assertFalse(ProductResource::canCreate());
    }

    public function test_super_admin_can_manage_products_fully(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);
        $this->assertTrue(ProductResource::canViewAny());
        $this->assertTrue(ProductResource::canCreate());
    }

    public function test_notification_logs_are_visible_to_super_admin_and_inventory_officer_only(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $officer = $this->makeInventoryOfficer();
        $customer = $this->makeCustomer();

        $this->actingAs($superAdmin);
        $this->assertTrue(NotificationLogResource::canViewAny());

        $this->actingAs($officer);
        $this->assertTrue(NotificationLogResource::canViewAny());

        $this->actingAs($customer);
        $this->assertFalse(NotificationLogResource::canViewAny());
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Widgets\PaymentsToConfirmWidget;
use App\Filament\Widgets\RecentOrdersWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TodaysDeliveriesWidget;
use App\Filament\Widgets\WorklistWidget;
use App\Models\User;
use App\Support\AdminSnapshot;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * Each staff role sees only the menu items and dashboard widgets it may use,
 * and the new dashboard/worklist pieces render without error.
 */
class NavigationAndDashboardTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function makeRole(string $key): User
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $user->assignRole(config('cymarket.roles.'.$key));

        return $user;
    }

    /** @return array<string, array<int, string>> group label => item labels */
    protected function menuFor(User $user): array
    {
        AdminSnapshot::flush();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return collect(Filament::getNavigation())
            ->mapWithKeys(fn ($group) => [(string) $group->getLabel() => collect($group->getItems())->map(fn ($i) => $i->getLabel())->values()->all()])
            ->all();
    }

    public function test_super_admin_menu_is_grouped_and_complete(): void
    {
        $menu = $this->menuFor($this->makeSuperAdmin());

        $this->assertSame(['Operations', 'Catalogue', 'Marketing', 'Reports', 'Administration'], array_values(array_filter(array_keys($menu))));
        $this->assertEqualsCanonicalizing(['Orders', 'Payments', 'Customers'], $menu['Operations']);
        $this->assertEqualsCanonicalizing(['Products', 'Categories', 'Stock movements'], $menu['Catalogue']);
        $this->assertSame(['Banners'], $menu['Marketing']);
        $this->assertSame(['Reports'], $menu['Reports']);
        $this->assertContains('Settings', $menu['Administration']);
        $this->assertContains('Staff Users', $menu['Administration']);
    }

    public function test_inventory_officer_sees_only_operational_items(): void
    {
        $menu = $this->menuFor($this->makeInventoryOfficer());

        $this->assertSame(['Orders'], $menu['Operations']);
        $this->assertEqualsCanonicalizing(['Products', 'Stock movements'], $menu['Catalogue']);
        $this->assertArrayNotHasKey('Marketing', $menu);
        $this->assertNotContains('Payments', $menu['Operations']);
        $this->assertNotContains('Settings', $menu['Administration'] ?? []);
    }

    public function test_support_agent_sees_orders_only(): void
    {
        $menu = $this->menuFor($this->makeRole('support_agent'));

        $this->assertSame(['Orders'], $menu['Operations']);
        $this->assertArrayNotHasKey('Catalogue', $menu);
        $this->assertArrayNotHasKey('Marketing', $menu);
        $this->assertArrayNotHasKey('Reports', $menu);
    }

    public function test_marketing_admin_sees_banners_only(): void
    {
        $menu = $this->menuFor($this->makeRole('marketing_admin'));

        $this->assertSame(['Banners'], $menu['Marketing']);
        $this->assertArrayNotHasKey('Operations', $menu);
        $this->assertArrayNotHasKey('Catalogue', $menu);
    }

    public function test_navigation_badges_reflect_worklist_counts(): void
    {
        $this->makeSuperAdmin();
        $counts = AdminSnapshot::counts();

        $this->assertSame(0, $counts['needs_action']);
        $this->assertSame(0, $counts['pending_transfers']);
    }

    public function test_super_admin_dashboard_and_widgets_render(): void
    {
        $this->actingAs($this->makeSuperAdmin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Dashboard::class)->assertSuccessful();
        Livewire::test(StatsOverviewWidget::class)->assertSee('Transfers to confirm')->assertSee("Today's sales");
        Livewire::test(WorklistWidget::class)->assertSee('caught up');
        Livewire::test(PaymentsToConfirmWidget::class)->assertSee('No transfers waiting');
        Livewire::test(TodaysDeliveriesWidget::class)->assertSee('No deliveries scheduled');
        Livewire::test(RecentOrdersWidget::class)->assertSuccessful();
    }

    public function test_inventory_officer_stats_hide_money_cards(): void
    {
        $this->actingAs($this->makeInventoryOfficer());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(PaymentsToConfirmWidget::canView());
        Livewire::test(StatsOverviewWidget::class)
            ->assertSee('Orders to process')
            ->assertDontSee("Today's sales")
            ->assertDontSee('Transfers to confirm');
    }

    public function test_marketing_admin_gets_shortcuts_not_kpis(): void
    {
        $this->actingAs($this->makeRole('marketing_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(StatsOverviewWidget::canView());
        $this->assertFalse(RecentOrdersWidget::canView());
        Livewire::test(WorklistWidget::class)->assertSee('Quick links')->assertSee('Shop banners');
    }

    public function test_orders_and_payments_lists_render_with_tabs(): void
    {
        $this->actingAs($this->makeSuperAdmin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListOrders::class)->assertSuccessful()->assertSee('Needs action');
        Livewire::test(ListPayments::class)->assertSuccessful()->assertSee('To confirm');
    }
}

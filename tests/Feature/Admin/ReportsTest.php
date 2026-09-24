<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Pages\Reports;
use App\Filament\Pages\Reports\CustomersReport;
use App\Filament\Pages\Reports\InventoryReport;
use App\Filament\Pages\Reports\OperationsReport;
use App\Filament\Pages\Reports\SalesReport;
use App\Models\Order;
use App\Services\ReportingService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * TOR §6.11 "Reporting & Management Dashboard" - Sales / Inventory /
 * Customers / Operations reports with export capability, gated per the
 * TOR §6.10 RBAC split (Inventory Officer: inventory only; Super Admin:
 * everything).
 */
class ReportsTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function makePaidOrder(int $quantity = 2, array $overrides = []): Order
    {
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 20, 'selling_price' => 20]);
        $customer = isset($overrides['user_id']) ? null : $this->makeCustomer();

        $order = Order::create(array_merge([
            'status' => OrderStatus::Paid,
            'user_id' => $customer?->id,
            'delivery_recipient_name' => 'Test Customer',
            'delivery_phone' => '+233200000001',
            'delivery_zone_id' => $zone->id,
            'delivery_address_line' => '1 Test Street',
            'delivery_fee' => (float) $zone->fee_override,
            'subtotal' => 20 * $quantity,
            'total' => 20 * $quantity + (float) $zone->fee_override,
            'placed_at' => now()->subHours(3),
            'paid_at' => now()->subHours(2),
            'dispatched_at' => now()->subHour(),
            'delivered_at' => now(),
        ], $overrides));

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 20,
            'quantity' => $quantity,
            'line_total' => 20 * $quantity,
        ]);

        $order->payments()->create([
            'provider' => 'mtn_momo',
            'amount' => $order->total,
            'status' => PaymentStatus::Successful,
            'paid_at' => now(),
        ]);

        return $order->fresh(['items', 'payments']);
    }

    public function test_only_super_admin_and_inventory_officer_can_access_the_reports_page(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $officer = $this->makeInventoryOfficer();
        $customer = $this->makeCustomer();

        $this->actingAs($superAdmin);
        $this->assertTrue(Reports::canAccess());

        $this->actingAs($officer);
        $this->assertTrue(Reports::canAccess());

        $this->actingAs($customer);
        $this->assertFalse(Reports::canAccess());
    }

    public function test_detail_report_pages_are_gated_the_same_way_as_their_hub_card(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $officer = $this->makeInventoryOfficer();
        $customer = $this->makeCustomer();

        $this->actingAs($superAdmin);
        $this->assertTrue(SalesReport::canAccess());
        $this->assertTrue(InventoryReport::canAccess());
        $this->assertTrue(CustomersReport::canAccess());
        $this->assertTrue(OperationsReport::canAccess());

        // Inventory Officer: only the operational report, not the
        // financial/commercial ones (TOR §6.10).
        $this->actingAs($officer);
        $this->assertFalse(SalesReport::canAccess());
        $this->assertTrue(InventoryReport::canAccess());
        $this->assertFalse(CustomersReport::canAccess());
        $this->assertFalse(OperationsReport::canAccess());

        $this->actingAs($customer);
        $this->assertFalse(SalesReport::canAccess());
        $this->assertFalse(InventoryReport::canAccess());
        $this->assertFalse(CustomersReport::canAccess());
        $this->assertFalse(OperationsReport::canAccess());
    }

    public function test_every_report_page_renders_for_the_roles_allowed_to_see_it(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $officer = $this->makeInventoryOfficer();
        $this->makePaidOrder(2);

        $this->actingAs($superAdmin);
        foreach ([Reports::getUrl(), SalesReport::getUrl(), InventoryReport::getUrl(), CustomersReport::getUrl(), OperationsReport::getUrl()] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($officer);
        $this->get(Reports::getUrl())->assertOk();
        $this->get(InventoryReport::getUrl())->assertOk();
        $this->get(SalesReport::getUrl())->assertForbidden();
    }

    public function test_report_pages_render_correctly_with_a_custom_date_range_in_the_filters(): void
    {
        $this->makePaidOrder(2);

        $this->actingAs($this->makeSuperAdmin())
            ->get(SalesReport::getUrl(['filters' => [
                'period' => 'custom',
                'from' => now()->subDays(3)->toDateString(),
                'to' => now()->toDateString(),
            ]]))
            ->assertOk()
            ->assertSee('Custom Range');
    }

    public function test_sales_summary_reports_gross_net_and_refunded_revenue(): void
    {
        // One kept order (2 x 20 + 15 delivery = 55) and one order that was
        // paid (1 x 20 + 15 = 35) and then cancelled, so it must be refunded.
        $this->makePaidOrder(2);
        $this->makePaidOrder(1, ['status' => OrderStatus::Cancelled]);

        $summary = app(ReportingService::class)->salesSummary(now()->subDay(), now()->addDay());

        $this->assertSame(1, $summary['order_count']);
        $this->assertEquals(55.0, $summary['net_revenue'], 'Net is what the business kept.');
        $this->assertEquals(35.0, $summary['refunded_amount']);
        $this->assertEquals(90.0, $summary['gross_revenue'], 'Gross is everything collected, refunded orders included.');
        $this->assertEquals($summary['gross_revenue'] - $summary['refunded_amount'], $summary['net_revenue']);
    }

    public function test_cancelled_orders_that_were_never_paid_are_not_counted_as_refunds(): void
    {
        $order = $this->makePaidOrder(1, ['status' => OrderStatus::Cancelled]);
        $order->payments()->update(['status' => PaymentStatus::Failed]);

        $summary = app(ReportingService::class)->salesSummary(now()->subDay(), now()->addDay());

        $this->assertEquals(0.0, $summary['refunded_amount']);
        $this->assertEquals(0.0, $summary['gross_revenue']);
        $this->assertEquals(0.0, $summary['net_revenue']);
    }

    public function test_sales_by_period_returns_one_row_per_day_including_empty_days(): void
    {
        $this->makePaidOrder(2);

        $rows = app(ReportingService::class)->salesByPeriod(now()->subDays(2)->startOfDay(), now()->endOfDay());

        $this->assertCount(3, $rows);
        $this->assertSame(0, $rows[0]->orders);
        $this->assertSame(0, $rows[1]->orders);
        $this->assertSame(now()->toDateString(), $rows[2]->day);
        $this->assertSame(1, $rows[2]->orders);
        $this->assertEquals(55.0, $rows[2]->revenue);
    }

    public function test_new_versus_returning_customers_are_scoped_to_the_period(): void
    {
        $returning = $this->makeCustomer();
        $firstTime = $this->makeCustomer();

        // "returning" bought 10 days ago and again today; "firstTime" only today.
        $old = $this->makePaidOrder(1, ['user_id' => $returning->id]);
        Order::whereKey($old->id)->update(['created_at' => now()->subDays(10)]);
        $this->makePaidOrder(1, ['user_id' => $returning->id]);
        $this->makePaidOrder(1, ['user_id' => $firstTime->id]);

        $report = app(ReportingService::class)->customerReport(now()->startOfDay(), now()->endOfDay());

        $this->assertSame(1, $report['returning_customers']);
        $this->assertSame(1, $report['first_time_buyers']);
        $this->assertSame(2, $report['new_customers'], 'Both accounts were registered today.');

        // Over a window that reaches back before the first order, nobody
        // has a purchase "before the period", so nobody is returning yet.
        $wide = app(ReportingService::class)->customerReport(now()->subDays(30), now()->endOfDay());
        $this->assertSame(0, $wide['returning_customers']);
        $this->assertSame(2, $wide['first_time_buyers']);
    }

    public function test_stock_levels_list_every_product_lowest_sellable_first(): void
    {
        $this->makeProduct(['name' => 'Plenty', 'available_quantity' => 100]);
        $this->makeProduct(['name' => 'Scarce', 'available_quantity' => 3]);

        $levels = app(ReportingService::class)->stockLevels();

        $this->assertSame(['Scarce', 'Plenty'], $levels->pluck('name')->all());
    }

    public function test_sales_by_product_and_by_location_are_scoped_to_the_period(): void
    {
        $order = $this->makePaidOrder(3);

        $reporting = app(ReportingService::class);

        $byProduct = $reporting->salesByProduct(now()->subDay(), now()->addDay());
        $this->assertCount(1, $byProduct);
        $this->assertEquals(3, $byProduct->first()->units_sold);

        $byLocation = $reporting->salesByLocation(now()->subDay(), now()->addDay());
        $this->assertSame($order->deliveryZone->name, $byLocation->first()->location);
    }

    public function test_spoilage_write_offs_surface_manual_negative_stock_adjustments(): void
    {
        $product = $this->makeProduct(['available_quantity' => 30]);
        $officer = $this->makeInventoryOfficer();

        app(StockService::class)->manualAdjustment($product, -5, 'Spoiled produce', $officer->id);
        app(StockService::class)->manualAdjustment($product, 10, 'Fresh delivery', $officer->id);

        $writeOffs = app(ReportingService::class)->spoilageWriteOffs(now()->subDay(), now()->addDay());

        $this->assertCount(1, $writeOffs);
        $this->assertSame(5, $writeOffs->first()->quantity);
        $this->assertSame('Spoiled produce', $writeOffs->first()->note);
    }

    public function test_fulfilment_performance_averages_lifecycle_hand_off_times(): void
    {
        $this->makePaidOrder(1);

        $metrics = app(ReportingService::class)->fulfilmentPerformance(now()->subDay(), now()->addDay());

        $this->assertEquals(1.0, $metrics['avg_hours_placed_to_paid']);
        $this->assertEquals(1.0, $metrics['avg_hours_paid_to_dispatched']);
        $this->assertEquals(1.0, $metrics['avg_hours_dispatched_to_delivered']);
        $this->assertEquals(0.0, $metrics['cancellation_rate']);
    }

    public function test_inventory_officer_can_export_inventory_but_not_sales_report(): void
    {
        $officer = $this->makeInventoryOfficer();
        $this->makeProduct();

        $this->actingAs($officer)
            ->get(route('admin.reports.export', 'inventory'))
            ->assertOk();

        $this->actingAs($officer)
            ->get(route('admin.reports.export', 'sales'))
            ->assertForbidden();
    }

    public function test_super_admin_can_export_every_report(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $this->makePaidOrder(1);

        $this->actingAs($superAdmin);

        foreach (['sales', 'inventory', 'customers', 'operations'] as $report) {
            $this->get(route('admin.reports.export', $report))->assertOk();
        }
    }

    public function test_customers_cannot_export_any_report(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer)
            ->get(route('admin.reports.export', 'inventory'))
            ->assertForbidden();
    }

    public function test_sales_export_contains_every_sub_report_the_tor_lists(): void
    {
        $this->makePaidOrder(2);

        $csv = $this->actingAs($this->makeSuperAdmin())
            ->get(route('admin.reports.export', ['report' => 'sales', 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        foreach (['REVENUE SUMMARY', 'SALES BY DAY', 'SALES BY PRODUCT', 'SALES BY CATEGORY', 'SALES BY LOCATION'] as $section) {
            $this->assertStringContainsString($section, $csv);
        }

        $this->assertStringContainsString('"Net revenue (GHS)",55.00', $csv);
        $this->assertStringContainsString('"Gross revenue (GHS)",55.00', $csv);
    }

    public function test_inventory_export_contains_stock_levels_write_offs_and_movement_history(): void
    {
        $officer = $this->makeInventoryOfficer();
        $product = $this->makeProduct(['name' => 'Tomatoes', 'available_quantity' => 30, 'selling_price' => 10]);
        app(StockService::class)->manualAdjustment($product, -4, 'Spoiled in transit', $officer->id);

        $csv = $this->actingAs($officer)
            ->get(route('admin.reports.export', 'inventory'))
            ->assertOk()
            ->streamedContent();

        foreach (['INVENTORY SUMMARY', 'CURRENT STOCK LEVELS', 'SPOILAGE AND WRITE-OFFS', 'STOCK MOVEMENT HISTORY'] as $section) {
            $this->assertStringContainsString($section, $csv);
        }

        $this->assertStringContainsString('Spoiled in transit', $csv);
        // 26 left x GHS 10 = 260.00 of stock value.
        $this->assertStringContainsString('260.00', $csv);
    }

    public function test_exports_neutralise_spreadsheet_formulas_in_user_supplied_text(): void
    {
        $customer = $this->makeCustomer(['name' => '=HYPERLINK("http://evil.test","click")']);
        $this->makePaidOrder(1, ['user_id' => $customer->id]);

        $csv = $this->actingAs($this->makeSuperAdmin())
            ->get(route('admin.reports.export', 'customers'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringNotContainsString("\n=HYPERLINK", $csv);
    }

    public function test_export_rejects_unparseable_dates_instead_of_erroring(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('admin.reports.export', ['report' => 'sales', 'from' => 'not-a-date']))
            ->assertSessionHasErrors('from');
    }

    public function test_export_treats_a_reversed_range_as_the_same_span(): void
    {
        $this->makePaidOrder(1);

        $csv = $this->actingAs($this->makeSuperAdmin())
            ->get(route('admin.reports.export', ['report' => 'sales', 'from' => now()->addDay()->toDateString(), 'to' => now()->subDay()->toDateString()]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"Paid orders",1', $csv);
    }

    public function test_unknown_report_is_a_404_for_staff(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('admin.reports.export', 'nonsense'))
            ->assertNotFound();
    }

    public function test_dashboard_widgets_are_gated_by_role(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $officer = $this->makeInventoryOfficer();
        $this->seedRoles();
        $marketing = \App\Models\User::factory()->create();
        $marketing->assignRole(config('cymarket.roles.marketing_admin'));

        $this->actingAs($superAdmin);
        $this->assertTrue(\App\Filament\Widgets\StatsOverviewWidget::canView());
        $this->assertTrue(\App\Filament\Widgets\SalesChartWidget::canView());
        $this->assertTrue(\App\Filament\Widgets\LowStockProductsWidget::canView());

        // Inventory Officer: operational widgets yes, revenue chart no.
        $this->actingAs($officer);
        $this->assertTrue(\App\Filament\Widgets\StatsOverviewWidget::canView());
        $this->assertTrue(\App\Filament\Widgets\LowStockProductsWidget::canView());
        $this->assertFalse(\App\Filament\Widgets\SalesChartWidget::canView());

        // Marketing Admin has no order, stock or financial access (TOR §6.10).
        $this->actingAs($marketing);
        $this->assertFalse(\App\Filament\Widgets\StatsOverviewWidget::canView());
        $this->assertFalse(\App\Filament\Widgets\SalesChartWidget::canView());
        $this->assertFalse(\App\Filament\Widgets\LowStockProductsWidget::canView());
        $this->assertFalse(Reports::canAccess());
    }
}

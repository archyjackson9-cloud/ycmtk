<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Filament\Pages\Reports\CustomersReport;
use App\Filament\Pages\Reports\InventoryReport;
use App\Filament\Pages\Reports\OperationsReport;
use App\Filament\Pages\Reports\SalesReport;
use App\Models\Order;
use App\Services\ReportingService;
use Filament\Pages\Page;

/**
 * Reporting & Management Dashboard hub (TOR §6.11): a grid of report
 * types, each linking through to its own detailed report page (period
 * filter + charts + tables + export). RBAC (TOR §6.10): Super Admin sees
 * every card; Inventory Officer sees Inventory only - Sales, Customers
 * and Operations are financial/commercial reports and stay hidden for
 * that role.
 */
class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.reports';

    protected static ?string $title = 'Reports';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            config('cymarket.roles.super_admin'),
            config('cymarket.roles.inventory_officer'),
        ]) ?? false;
    }

    protected function service(): ReportingService
    {
        return app(ReportingService::class);
    }

    /**
     * @return array<int, array{title: string, description: string, icon: string, color: string, url: string, statLabel: string, statValue: string}>
     */
    public function cards(): array
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $month = [now()->startOfMonth(), now()->endOfMonth()];
        $cards = [];

        if ($isSuperAdmin) {
            $sales = $this->service()->salesSummary(...$month);

            $cards[] = [
                'title' => 'Sales',
                'description' => 'Revenue by period, product, category and delivery location.',
                'icon' => 'heroicon-o-banknotes',
                'color' => 'cy-badge-emerald',
                'url' => SalesReport::getUrl(),
                'statLabel' => 'This month, net',
                'statValue' => 'GHS '.number_format($sales['net_revenue'], 2),
            ];
        }

        $inventory = $this->service()->inventoryReport();

        $cards[] = [
            'title' => 'Inventory',
            'description' => 'Stock levels, low-stock alerts, write-offs and movement history.',
            'icon' => 'heroicon-o-archive-box',
            'color' => 'cy-badge-amber',
            'url' => InventoryReport::getUrl(),
            'statLabel' => 'Low on stock',
            'statValue' => (string) $inventory['low_stock'],
        ];

        if ($isSuperAdmin) {
            $customers = $this->service()->customerReport(...$month);

            $cards[] = [
                'title' => 'Customers',
                'description' => 'New vs returning buyers, most active and lifetime value.',
                'icon' => 'heroicon-o-user-group',
                'color' => 'cy-badge-sky',
                'url' => CustomersReport::getUrl(),
                'statLabel' => 'New this month',
                'statValue' => (string) $customers['new_customers'],
            ];

            $awaitingAction = Order::whereIn('status', [OrderStatus::PendingPayment, OrderStatus::Paid])->count();

            $cards[] = [
                'title' => 'Operations',
                'description' => 'Orders by status, revenue and fulfilment performance.',
                'icon' => 'heroicon-o-truck',
                'color' => 'cy-badge-violet',
                'url' => OperationsReport::getUrl(),
                'statLabel' => 'Awaiting action',
                'statValue' => (string) $awaitingAction,
            ];
        }

        return $cards;
    }
}

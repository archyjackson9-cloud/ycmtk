<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\ReportingService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top-of-dashboard KPI cards (TOR §6.11 Reporting & Management Dashboard).
 *
 * RBAC (TOR §6.10): Super Admin sees every card. The Inventory Officer sees
 * the operational cards only (orders awaiting action, low stock) - sales
 * revenue and customer counts are financial/commercial. Marketing and
 * Support roles have no order, stock or financial access, so they get no
 * cards at all. Figures are intentionally coarse (today + pending counts)
 * so they load fast.
 */
class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole([
            config('cymarket.roles.super_admin'),
            config('cymarket.roles.inventory_officer'),
        ]) ?? false;
    }

    protected function getStats(): array
    {
        $reporting = app(ReportingService::class);
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        $inventory = $reporting->inventoryReport();
        $pendingOrders = Order::whereIn('status', [OrderStatus::PendingPayment, OrderStatus::Paid])->count();

        $stats = [];

        if ($isSuperAdmin) {
            $todaySales = $reporting->salesSummary(now()->startOfDay(), now()->endOfDay());

            $stats[] = Stat::make("Today's Sales", 'GHS '.number_format($todaySales['net_revenue'], 2))
                ->description($todaySales['order_count'].' paid order(s) today, net of refunds')
                ->color('success');
        }

        $stats[] = Stat::make('Orders Awaiting Action', (string) $pendingOrders)
            ->description('Pending payment or awaiting processing')
            ->color($pendingOrders > 0 ? 'warning' : 'success');

        $stats[] = Stat::make('Low Stock Products', (string) $inventory['low_stock'])
            ->description($inventory['out_of_stock'].' fully out of stock')
            ->color($inventory['low_stock'] > 0 ? 'danger' : 'success');

        if ($isSuperAdmin) {
            $newCustomersToday = $reporting->customerReport(now()->startOfDay(), now()->endOfDay())['new_customers'];

            $stats[] = Stat::make('New Customers Today', (string) $newCustomersToday)
                ->description('Registered accounts with the Customer role')
                ->color('info');
        }

        return $stats;
    }
}

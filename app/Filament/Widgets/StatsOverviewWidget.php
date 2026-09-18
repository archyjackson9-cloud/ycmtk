<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\ReportingService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top-of-dashboard KPI cards (TOR §6.11 Reporting & Management Dashboard).
 * Visible to any staff role that can open the panel; figures are
 * intentionally coarse (today + pending counts) so they load fast.
 */
class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $reporting = app(ReportingService::class);

        $todaySales = $reporting->salesSummary(now()->startOfDay(), now()->endOfDay());
        $inventory = $reporting->inventoryReport();

        $pendingOrders = Order::whereIn('status', [OrderStatus::PendingPayment, OrderStatus::Paid])->count();
        $newCustomersToday = $reporting->customerReport(now()->startOfDay(), now()->endOfDay())['new_customers'];

        return [
            Stat::make("Today's Sales", 'GHS '.number_format($todaySales['gross_revenue'], 2))
                ->description($todaySales['order_count'].' paid order(s) today')
                ->color('success'),

            Stat::make('Orders Awaiting Action', (string) $pendingOrders)
                ->description('Pending payment or awaiting processing')
                ->color($pendingOrders > 0 ? 'warning' : 'success'),

            Stat::make('Low Stock Products', (string) $inventory['low_stock'])
                ->description($inventory['out_of_stock'].' fully out of stock')
                ->color($inventory['low_stock'] > 0 ? 'danger' : 'success'),

            Stat::make('New Customers Today', (string) $newCustomersToday)
                ->description('Registered accounts with the Customer role')
                ->color('info'),
        ];
    }
}

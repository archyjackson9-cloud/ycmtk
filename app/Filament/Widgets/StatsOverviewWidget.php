<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\ProductResource;
use App\Support\AdminSnapshot;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top-of-dashboard KPI cards (TOR §6.11 Reporting & Management Dashboard).
 *
 * Each card is shown only when the role may open the screen it links to
 * (TOR §6.10): sales = Super Admin; orders/deliveries = anyone who can see
 * Orders; transfers to confirm = whoever can open Payments; low stock =
 * whoever can open Products. Numbers come from AdminSnapshot, which caches
 * briefly and shares one query set with the nav badges. Polling is off
 * (audit A-01): the page refreshes on navigation, and the bell polls.
 */
class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return OrderResource::canViewAny() || ProductResource::canViewAny();
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $counts = AdminSnapshot::counts();
        $stats = [];

        if ($user?->isSuperAdmin()) {
            $sales = AdminSnapshot::sales();

            foreach (['today' => "Today's sales", 'week' => 'This week', 'month' => 'This month'] as $key => $label) {
                $stats[] = Stat::make($label, 'GHS '.number_format($sales[$key]['net_revenue'], 2))
                    ->description($sales[$key]['order_count'].' paid '.($sales[$key]['order_count'] === 1 ? 'order' : 'orders'))
                    ->descriptionIcon('heroicon-m-banknotes')
                    ->color('success');
            }
        }

        if (OrderResource::canViewAny()) {
            $stats[] = Stat::make('Orders to process', (string) $counts['needs_action'])
                ->description($counts['needs_action'] > 0 ? 'Paid or on hold - start here' : 'Nothing waiting')
                ->descriptionIcon($counts['needs_action'] > 0 ? 'heroicon-m-bolt' : 'heroicon-m-check-circle')
                ->color($counts['needs_action'] > 0 ? 'warning' : 'success')
                ->url(OrderResource::getUrl('index', ['activeTab' => 'needs_action']));
        }

        if (PaymentResource::canViewAny()) {
            $stats[] = Stat::make('Transfers to confirm', (string) $counts['pending_transfers'])
                ->description($counts['pending_transfers'] > 0 ? 'Check the account, then confirm' : 'All confirmed')
                ->descriptionIcon($counts['pending_transfers'] > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($counts['pending_transfers'] > 0 ? 'warning' : 'success')
                ->url(PaymentResource::getUrl('index', ['activeTab' => 'to_confirm']));
        }

        if (OrderResource::canViewAny()) {
            $stats[] = Stat::make("Today's deliveries", (string) $counts['deliveries_today'])
                ->description('Scheduled for today, not yet delivered')
                ->descriptionIcon('heroicon-m-truck')
                ->color($counts['deliveries_today'] > 0 ? 'info' : 'gray')
                ->url(OrderResource::getUrl('index', ['activeTab' => 'today']));
        }

        if (ProductResource::canViewAny()) {
            $attention = $counts['low_stock'] + $counts['out_of_stock'] > 0;

            $stats[] = Stat::make('Low stock', (string) $counts['low_stock'])
                ->description($counts['out_of_stock'].' out of stock')
                ->descriptionIcon($attention ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($attention ? 'danger' : 'success')
                ->url(ProductResource::getUrl('index', ['tableFilters' => ['low_stock' => ['isActive' => true]]]));
        }

        return $stats;
    }
}

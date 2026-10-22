<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BannerResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\StockMovementResource;
use App\Support\AdminSnapshot;
use Filament\Widgets\Widget;

/**
 * "Needs your attention": a short, role-aware to-do list with one tap
 * through to the filtered screen where the work is done, plus shortcuts so
 * roles without KPIs (Marketing Admin) never land on an empty page.
 * Counts come from the cached AdminSnapshot - no per-render queries.
 */
class WorklistWidget extends Widget
{
    protected static ?int $sort = 2;

    protected static string $view = 'filament.widgets.worklist';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<int, array{label: string, hint: string, count: int, url: string, color: string, icon: string}>
     */
    public function getTasks(): array
    {
        $counts = AdminSnapshot::counts();
        $onHold = $counts['by_status']['on_hold'] ?? 0;
        $paid = $counts['by_status']['paid'] ?? 0;
        $tasks = [];

        if (PaymentResource::canViewAny()) {
            $tasks[] = ['label' => 'Transfers to confirm', 'hint' => 'Customers who paid by transfer are waiting for you to check the account.', 'count' => $counts['pending_transfers'], 'url' => PaymentResource::getUrl('index', ['activeTab' => 'to_confirm']), 'color' => 'warning', 'icon' => 'heroicon-o-banknotes'];
        }

        if (OrderResource::canViewAny()) {
            $ordersUrl = OrderResource::getUrl('index', ['activeTab' => 'needs_action']);

            $tasks[] = ['label' => 'Paid orders to start processing', 'hint' => 'Payment is confirmed. Pick and pack these.', 'count' => $paid, 'url' => $ordersUrl, 'color' => 'info', 'icon' => 'heroicon-o-shopping-cart'];
            $tasks[] = ['label' => 'Orders on hold', 'hint' => 'Something is wrong (usually stock). Decide whether to continue or cancel.', 'count' => $onHold, 'url' => $ordersUrl, 'color' => 'danger', 'icon' => 'heroicon-o-pause-circle'];
            $tasks[] = ['label' => "Today's deliveries", 'hint' => 'Scheduled for today and not delivered yet.', 'count' => $counts['deliveries_today'], 'url' => OrderResource::getUrl('index', ['activeTab' => 'today']), 'color' => 'primary', 'icon' => 'heroicon-o-truck'];
        }

        if (ProductResource::canViewAny()) {
            $tasks[] = ['label' => 'Products low or out of stock', 'hint' => 'Restock before customers hit "out of stock".', 'count' => $counts['low_stock'] + $counts['out_of_stock'], 'url' => ProductResource::getUrl('index', ['tableFilters' => ['low_stock' => ['isActive' => true]]]), 'color' => 'danger', 'icon' => 'heroicon-o-exclamation-triangle'];
        }

        return $tasks;
    }

    /**
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    public function getShortcuts(): array
    {
        $shortcuts = [];

        foreach ([
            [OrderResource::class, 'All orders', 'heroicon-o-shopping-cart'],
            [ProductResource::class, 'Products', 'heroicon-o-shopping-bag'],
            [StockMovementResource::class, 'Stock movements', 'heroicon-o-archive-box'],
            [BannerResource::class, 'Shop banners', 'heroicon-o-rectangle-stack'],
        ] as [$resource, $label, $icon]) {
            if ($resource::canViewAny()) {
                $shortcuts[] = ['label' => $label, 'url' => $resource::getUrl('index'), 'icon' => $icon];
            }
        }

        return $shortcuts;
    }
}

<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Support\AdminSnapshot;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orders as a worklist: one tab per stage of the shop day, each with a live
 * count (a single cached grouped query, see AdminSnapshot). Opens on "Needs
 * action" when there is something waiting, otherwise on everything.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return AdminSnapshot::counts()['needs_action'] > 0 ? 'needs_action' : 'all';
    }

    public function getTabs(): array
    {
        $counts = AdminSnapshot::counts();
        $by = fn (OrderStatus ...$statuses): int => collect($statuses)->sum(fn (OrderStatus $s) => $counts['by_status'][$s->value] ?? 0);

        return [
            'needs_action' => Tab::make('Needs action')
                ->icon('heroicon-m-bolt')
                ->badge($counts['needs_action'] ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', AdminSnapshot::NEEDS_ACTION_STATUSES)),
            'today' => Tab::make('Delivering today')
                ->icon('heroicon-m-truck')
                ->badge($counts['deliveries_today'] ?: null)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => AdminSnapshot::applyDeliveringToday($query)->reorder('scheduled_delivery_at')),
            'awaiting_payment' => Tab::make('Awaiting payment')
                ->badge($by(OrderStatus::PendingPayment) ?: null)
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::PendingPayment)),
            'processing' => Tab::make('Processing')
                ->badge($by(OrderStatus::Processing) ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Processing)),
            'out_for_delivery' => Tab::make('Out for delivery')
                ->badge($by(OrderStatus::Dispatched) ?: null)
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Dispatched)),
            'done' => Tab::make('Delivered')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [OrderStatus::Delivered, OrderStatus::Completed])),
            'cancelled' => Tab::make('Cancelled')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Cancelled)),
            'all' => Tab::make('All orders'),
        ];
    }
}

<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource;
use App\Support\AdminSnapshot;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    /** Land on the worklist when there is something to confirm, otherwise show everything. */
    public function getDefaultActiveTab(): string|int|null
    {
        return AdminSnapshot::counts()['pending_transfers'] > 0 ? 'to_confirm' : 'all';
    }

    public function getTabs(): array
    {
        $pending = AdminSnapshot::counts()['pending_transfers'];

        return [
            'to_confirm' => Tab::make('To confirm')
                ->icon('heroicon-m-clock')
                ->badge($pending ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('provider', 'manual')->where('status', PaymentStatus::Pending)->orderBy('created_at')),
            'all' => Tab::make('All'),
            'confirmed' => Tab::make('Confirmed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PaymentStatus::Successful)),
            'rejected' => Tab::make('Failed / rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [PaymentStatus::Failed, PaymentStatus::Cancelled])),
        ];
    }
}

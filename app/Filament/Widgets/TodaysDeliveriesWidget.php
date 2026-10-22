<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Support\AdminSnapshot;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Today's delivery run (orders with a scheduled_delivery_at today that are
 * not yet delivered), soonest first, with a tap-to-call number for riders.
 */
class TodaysDeliveriesWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = "Today's deliveries";

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return OrderResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AdminSnapshot::deliveriesTodayQuery()
                    ->with(['user:id,name,phone', 'deliveryZone:id,name'])
                    ->orderBy('scheduled_delivery_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_delivery_at')->label('Time')->dateTime('H:i')->weight('bold'),
                Tables\Columns\TextColumn::make('order_number')->label('Order')->color('primary')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('customer')->state(fn (Order $record) => $record->customerName()),
                Tables\Columns\TextColumn::make('delivery_phone')->label('Phone')->icon('heroicon-m-phone')->url(fn (Order $record) => 'tel:'.$record->delivery_phone),
                Tables\Columns\TextColumn::make('deliveryZone.name')->label('Zone')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
            ])
            ->actions([
                Tables\Actions\Action::make('open')->label('Open')->icon('heroicon-o-eye')->color('gray')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No deliveries scheduled for today')
            ->emptyStateDescription('Orders with a delivery slot for today will be listed here until they are delivered.');
    }
}

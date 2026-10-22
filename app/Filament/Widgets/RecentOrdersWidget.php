<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/** The latest orders at a glance, newest first. */
class RecentOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Recent orders';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return OrderResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->with('user:id,name,phone')->latest()->limit(8))
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('Order')->weight('bold')->color('primary')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('customer')->state(fn (Order $record) => $record->customerName()),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                Tables\Columns\TextColumn::make('total')->money('GHS'),
                Tables\Columns\TextColumn::make('created_at')->label('Placed')->since()->dateTimeTooltip('d M Y, H:i'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('all')->label('See all orders')->url(OrderResource::getUrl('index'))->color('gray')->size('sm'),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Orders will appear here as soon as customers check out.');
    }
}

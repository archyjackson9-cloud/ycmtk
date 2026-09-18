<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Services\ReportingService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Reorder worklist for the Inventory Officer (TOR §6.6 low-stock alerts,
 * §6.11 dashboard). Reuses ReportingService::lowStockProducts() so the
 * threshold logic lives in exactly one place.
 */
class LowStockProductsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Low Stock Products';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()->whereIn('id', app(ReportingService::class)->lowStockProducts(50)->pluck('id'))
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('category.name')->label('Category'),
                Tables\Columns\TextColumn::make('sellable_quantity')
                    ->label('Sellable Qty')
                    ->badge()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('low_stock_threshold')->label('Threshold')->placeholder('Default'),
                Tables\Columns\TextColumn::make('unit_of_measurement')->label('Unit'),
            ])
            ->actions([
                Tables\Actions\Action::make('viewProduct')
                    ->label('Restock')
                    ->url(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-o-arrow-path'),
            ])
            ->paginated(false);
    }
}

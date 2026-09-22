<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StockMovementResource;
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

    /** Inventory reports: Super Admin and Inventory Officer (TOR §6.10). */
    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole([
            config('cymarket.roles.super_admin'),
            config('cymarket.roles.inventory_officer'),
        ]) ?? false;
    }

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
                // Stock is adjusted through an audited stock movement, which
                // both Super Admin and Inventory Officer may create; product
                // editing is Super Admin only, so linking there would 403.
                Tables\Actions\Action::make('restock')
                    ->label('Restock')
                    ->url(fn (Product $record) => StockMovementResource::getUrl('create', ['product_id' => $record->id]))
                    ->icon('heroicon-o-arrow-path'),
            ])
            ->paginated(false);
    }
}

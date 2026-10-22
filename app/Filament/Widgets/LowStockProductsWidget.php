<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\StockMovementResource;
use App\Models\Product;
use App\Services\ReportingService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Reorder worklist for the Inventory Officer (TOR §6.6 low-stock alerts,
 * §6.11 dashboard). Reuses ReportingService::lowStockProducts() so the
 * threshold logic lives in exactly one place; the table re-sorts the same
 * ids lowest-stock-first (audit A-07 - the order used to be lost).
 */
class LowStockProductsWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Low stock';

    /** Inventory reports: Super Admin and Inventory Officer (TOR §6.10). */
    public static function canView(): bool
    {
        return ProductResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->with('category:id,name')
                    ->whereIn('id', app(ReportingService::class)->lowStockProducts(50)->pluck('id'))
                    ->orderByRaw('(available_quantity - reserved_quantity) asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sellable_quantity')
                    ->label('Left to sell')
                    ->badge()
                    ->color(fn (Product $record) => $record->sellable_quantity <= 0 ? 'danger' : 'warning')
                    ->formatStateUsing(fn (Product $record, $state) => $state.' '.$record->unit_of_measurement),
                Tables\Columns\TextColumn::make('low_stock_threshold')->label('Alert at')->placeholder('Default')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                // Stock is adjusted through an audited stock movement, which
                // both Super Admin and Inventory Officer may create; product
                // editing is Super Admin only, so linking there would 403.
                Tables\Actions\Action::make('restock')
                    ->label('Restock')
                    ->url(fn (Product $record) => StockMovementResource::getUrl('create', ['product_id' => $record->id]))
                    ->icon('heroicon-o-archive-box-arrow-down'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('all')
                    ->label('All low-stock products')
                    ->url(ProductResource::getUrl('index', ['tableFilters' => ['low_stock' => ['isActive' => true]]]))
                    ->color('gray')
                    ->size('sm'),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('All stocked up')
            ->emptyStateDescription('Nothing is running low right now.');
    }
}

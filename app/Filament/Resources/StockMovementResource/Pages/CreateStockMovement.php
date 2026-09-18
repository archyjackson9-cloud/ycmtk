<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use App\Models\Product;
use App\Services\StockService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;

    /**
     * Route creation through StockService so the product's on-hand
     * quantity is actually adjusted, not just the audit row inserted.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $product = Product::findOrFail($data['product_id']);

        app(StockService::class)->manualAdjustment(
            $product,
            (int) $data['delta'],
            $data['note'],
            auth()->id(),
        );

        return $product->stockMovements()->latest()->firstOrFail();
    }
}

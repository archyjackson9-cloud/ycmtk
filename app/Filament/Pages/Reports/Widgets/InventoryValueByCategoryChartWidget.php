<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Services\ReportingService;
use Filament\Widgets\ChartWidget;

/**
 * Stock valuation by category, on the Inventory report page. Not
 * period-scoped - stock on hand is always "now" (matches the note already
 * shown next to the Inventory KPI tiles).
 */
class InventoryValueByCategoryChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Stock Value by Category';

    protected function getData(): array
    {
        $rows = app(ReportingService::class)->inventoryValuationByCategory()->take(8);

        return [
            'datasets' => [[
                'label' => 'Stock Value (GHS)',
                'data' => $rows->pluck('valuation')->toArray(),
                'backgroundColor' => '#15803d',
                'borderRadius' => 6,
            ]],
            'labels' => $rows->pluck('category')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

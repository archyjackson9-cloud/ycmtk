<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Services\ReportingService;
use App\Support\ReportPeriod;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Revenue by category for the selected period, on the Sales report page.
 */
class SalesByCategoryChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Revenue by Category';

    protected function getData(): array
    {
        [$from, $to] = ReportPeriod::range($this->filters);
        $rows = app(ReportingService::class)->salesByCategory($from, $to)->take(8);

        return [
            'datasets' => [[
                'label' => 'Revenue (GHS)',
                'data' => $rows->pluck('revenue')->toArray(),
                'backgroundColor' => '#f59e0b',
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

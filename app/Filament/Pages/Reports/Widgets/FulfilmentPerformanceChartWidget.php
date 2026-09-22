<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Services\ReportingService;
use App\Support\ReportPeriod;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Average hours spent at each lifecycle hand-off, on the Operations report
 * page.
 */
class FulfilmentPerformanceChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Fulfilment Performance (avg hours)';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        [$from, $to] = ReportPeriod::range($this->filters);
        $f = app(ReportingService::class)->fulfilmentPerformance($from, $to);

        return [
            'datasets' => [[
                'label' => 'Avg hours',
                'data' => [
                    $f['avg_hours_placed_to_paid'] ?? 0,
                    $f['avg_hours_paid_to_dispatched'] ?? 0,
                    $f['avg_hours_dispatched_to_delivered'] ?? 0,
                    $f['avg_hours_placed_to_delivered'] ?? 0,
                ],
                'backgroundColor' => '#f59e0b',
                'borderRadius' => 6,
            ]],
            'labels' => ['Placed → Paid', 'Paid → Dispatched', 'Dispatched → Delivered', 'Placed → Delivered'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

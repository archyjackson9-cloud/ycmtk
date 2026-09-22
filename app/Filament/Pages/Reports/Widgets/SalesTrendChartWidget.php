<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Services\ReportingService;
use App\Support\ReportPeriod;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Daily revenue trend for the selected period, on the Sales report page.
 */
class SalesTrendChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Revenue Trend';

    protected function getData(): array
    {
        [$from, $to] = ReportPeriod::range($this->filters);
        $rows = app(ReportingService::class)->salesByPeriod($from, $to);

        return [
            'datasets' => [[
                'label' => 'Revenue (GHS)',
                'data' => $rows->pluck('revenue')->toArray(),
                'borderColor' => '#16a34a',
                'backgroundColor' => 'rgba(22, 163, 74, 0.14)',
                'fill' => true,
                'tension' => 0.35,
            ]],
            'labels' => $rows->map(fn ($row) => Carbon::parse($row->day)->format('d M'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

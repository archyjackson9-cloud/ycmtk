<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Enums\OrderStatus;
use App\Services\ReportingService;
use App\Support\ReportPeriod;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Orders by lifecycle status for the selected period, on the Operations
 * report page.
 */
class OperationsByStatusChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Orders by Status';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        [$from, $to] = ReportPeriod::range($this->filters);
        $ops = app(ReportingService::class)->operationsReport($from, $to);
        $cases = collect(OrderStatus::cases());

        return [
            'datasets' => [[
                'label' => 'Orders',
                'data' => $cases->map(fn (OrderStatus $status) => $ops[$status->value] ?? 0)->toArray(),
                'backgroundColor' => '#0ea5e9',
                'borderRadius' => 6,
            ]],
            'labels' => $cases->map(fn (OrderStatus $status) => $status->label())->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

<?php

namespace App\Filament\Widgets;

use App\Services\ReportingService;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

/**
 * 14-day revenue trend (TOR §6.11). Uses ReportingService::salesByDay()
 * so the "paid" status set stays defined in one place.
 */
class SalesChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Sales — last 14 days';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $rows = app(ReportingService::class)->salesByDay(14);

        // Fill in any day with no orders so the chart doesn't skip gaps.
        $days = collect(range(0, 13))->map(fn (int $i) => now()->subDays(13 - $i)->format('Y-m-d'));

        $byDay = $rows->keyBy('day');

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (GHS)',
                    'data' => $days->map(fn (string $day) => (float) ($byDay[$day]->revenue ?? 0))->toArray(),
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5, 150, 105, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $days->map(fn (string $day) => Carbon::parse($day)->format('d M'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

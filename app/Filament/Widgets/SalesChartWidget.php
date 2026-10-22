<?php

namespace App\Filament\Widgets;

use App\Services\ReportingService;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

/**
 * 14-day revenue trend (TOR §6.11). Uses ReportingService::salesByDay()
 * so the "paid" status set stays defined in one place. No polling (audit
 * A-01) and the single query is cached for a minute.
 */
class SalesChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Sales - last 14 days';

    protected static ?string $description = 'Paid orders per day, in GHS.';

    protected static ?int $sort = 6;

    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 'full';

    /** Revenue is a financial report - Super Admin only (TOR §6.10). */
    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function getData(): array
    {
        $rows = Cache::remember('admin.sales_chart.14', 60, fn () => app(ReportingService::class)->salesByDay(14));

        // Fill in any day with no orders so the chart doesn't skip gaps.
        $days = collect(range(0, 13))->map(fn (int $i) => now()->subDays(13 - $i)->format('Y-m-d'));

        $byDay = $rows->keyBy('day');

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (GHS)',
                    'data' => $days->map(fn (string $day) => (float) ($byDay[$day]->revenue ?? 0))->toArray(),
                    'borderColor' => '#15803d',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 3,
                ],
            ],
            'labels' => $days->map(fn (string $day) => Carbon::parse($day)->format('d M'))->toArray(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => 'GHS ' + Number(ctx.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) } },
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: (value) => 'GHS ' + Number(value).toLocaleString() } },
            },
        }
        JS);
    }

    protected function getType(): string
    {
        return 'line';
    }
}

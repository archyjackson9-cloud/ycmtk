<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Services\ReportingService;
use App\Support\ReportPeriod;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * New accounts vs first-time buyers vs returning buyers for the selected
 * period, on the Customers report page.
 */
class CustomerAcquisitionChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Acquisition Breakdown';

    protected function getData(): array
    {
        [$from, $to] = ReportPeriod::range($this->filters);
        $report = app(ReportingService::class)->customerReport($from, $to);

        return [
            'datasets' => [[
                'label' => 'Customers',
                'data' => [$report['new_customers'], $report['first_time_buyers'], $report['returning_customers']],
                'backgroundColor' => ['#16a34a', '#f59e0b', '#0ea5e9'],
                'borderRadius' => 6,
            ]],
            'labels' => ['New Accounts', 'First-Time Buyers', 'Returning Buyers'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

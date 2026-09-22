<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports\Concerns\HasReportPeriodFilter;
use App\Services\ReportingService;
use Filament\Pages\Page;

/**
 * Sales report (TOR §6.11 "Sales - by period, by product, by category, by
 * location, revenue totals"). Financial report - Super Admin only.
 */
class SalesReport extends Page
{
    use HasReportPeriodFilter;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'reports/sales';

    protected static string $view = 'filament.pages.reports.sales-report';

    protected static ?string $title = 'Sales Report';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function service(): ReportingService
    {
        return app(ReportingService::class);
    }

    public function salesSummary(): array
    {
        [$from, $to] = $this->reportDateRange();

        return $this->service()->salesSummary($from, $to);
    }

    public function salesByProduct()
    {
        [$from, $to] = $this->reportDateRange();

        return $this->service()->salesByProduct($from, $to, 15);
    }

    public function salesByLocation()
    {
        [$from, $to] = $this->reportDateRange();

        return $this->service()->salesByLocation($from, $to);
    }

    public function exportUrl(): string
    {
        [$from, $to] = $this->reportDateRange();

        return route('admin.reports.export', ['report' => 'sales', 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }
}

<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports\Concerns\HasReportPeriodFilter;
use App\Services\ReportingService;
use Filament\Pages\Page;

/**
 * Operations report (TOR §6.11 "Operations - orders pending/processing/
 * dispatched/completed/cancelled, gross and net-of-refunds revenue,
 * fulfilment performance metrics"). Financial/commercial - Super Admin
 * only.
 */
class OperationsReport extends Page
{
    use HasReportPeriodFilter;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'reports/operations';

    protected static string $view = 'filament.pages.reports.operations-report';

    protected static ?string $title = 'Operations Report';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function service(): ReportingService
    {
        return app(ReportingService::class);
    }

    public function operationsReport(): array
    {
        [$from, $to] = $this->reportDateRange();

        return $this->service()->operationsReport($from, $to);
    }

    public function fulfilmentPerformance(): array
    {
        [$from, $to] = $this->reportDateRange();

        return $this->service()->fulfilmentPerformance($from, $to);
    }

    public function exportUrl(): string
    {
        [$from, $to] = $this->reportDateRange();

        return route('admin.reports.export', ['report' => 'operations', 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }
}

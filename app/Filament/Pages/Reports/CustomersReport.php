<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports\Concerns\HasReportPeriodFilter;
use App\Services\ReportingService;
use Filament\Pages\Page;

/**
 * Customers report (TOR §6.11 "Customers - new vs returning, most active,
 * basic lifetime-value indicators"). Financial/commercial - Super Admin
 * only.
 */
class CustomersReport extends Page
{
    use HasReportPeriodFilter;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'reports/customers';

    protected static string $view = 'filament.pages.reports.customers-report';

    protected static ?string $title = 'Customers Report';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function service(): ReportingService
    {
        return app(ReportingService::class);
    }

    public function customerReport(): array
    {
        [$from, $to] = $this->reportDateRange();

        return $this->service()->customerReport($from, $to);
    }

    public function mostActiveCustomers()
    {
        [$from, $to] = $this->reportDateRange();

        return $this->service()->mostActiveCustomers($from, $to, 10);
    }

    public function customerLifetimeValue()
    {
        return $this->service()->customerLifetimeValue(10);
    }

    public function exportUrl(): string
    {
        [$from, $to] = $this->reportDateRange();

        return route('admin.reports.export', ['report' => 'customers', 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }
}

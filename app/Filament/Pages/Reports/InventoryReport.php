<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports\Concerns\HasReportPeriodFilter;
use App\Services\ReportingService;
use Filament\Pages\Page;

/**
 * Inventory report (TOR §6.11 "Inventory - current stock levels,
 * low-stock alerts, spoilage/write-offs, stock movement history,
 * inventory valuation"). Open to Super Admin and Inventory Officer.
 */
class InventoryReport extends Page
{
    use HasReportPeriodFilter;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'reports/inventory';

    protected static string $view = 'filament.pages.reports.inventory-report';

    protected static ?string $title = 'Inventory Report';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            config('cymarket.roles.super_admin'),
            config('cymarket.roles.inventory_officer'),
        ]) ?? false;
    }

    protected function service(): ReportingService
    {
        return app(ReportingService::class);
    }

    public function inventoryReport(): array
    {
        return $this->service()->inventoryReport();
    }

    public function stockLevels()
    {
        return $this->service()->stockLevels(15);
    }

    public function lowStockProducts()
    {
        return $this->service()->lowStockProducts(15);
    }

    public function spoilageWriteOffs()
    {
        [$from, $to] = $this->reportDateRange();

        return $this->service()->spoilageWriteOffs($from, $to, 15);
    }

    public function stockMovementHistory()
    {
        [$from, $to] = $this->reportDateRange();

        return $this->service()->stockMovementHistory($from, $to, 15);
    }

    public function exportUrl(): string
    {
        [$from, $to] = $this->reportDateRange();

        return route('admin.reports.export', ['report' => 'inventory', 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }
}

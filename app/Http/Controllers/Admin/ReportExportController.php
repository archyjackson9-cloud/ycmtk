<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export for the admin Reports page (TOR §6.11 "... with daily / weekly
 * / monthly filters and export capability"). Hand-rolled streamed CSV -
 * deliberately no Composer export package, to keep the dependency surface
 * (and the composer-resolution risk that comes with it) as small as
 * possible. Gated by the same RBAC the Reports page itself uses (TOR
 * §6.10): Sales/Customers/Operations are Super-Admin only, Inventory is
 * open to Super Admin and Inventory Officer alike.
 *
 * Each export is a single CSV holding every sub-report the TOR lists for
 * that group, as titled sections separated by a blank line, so one
 * download carries the same content as the on-screen section.
 */
class ReportExportController extends Controller
{
    protected const FINANCIAL_REPORTS = ['sales', 'customers', 'operations'];

    /** Row cap for the long, period-scoped listings inside a CSV. */
    protected const ROW_LIMIT = 5000;

    public function __invoke(Request $request, string $report): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403);

        if (in_array($report, self::FINANCIAL_REPORTS, true)) {
            abort_unless($user->isSuperAdmin(), 403);
        } else {
            abort_unless($user->hasAnyRole([
                config('cymarket.roles.super_admin'),
                config('cymarket.roles.inventory_officer'),
            ]), 403);
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        [$from, $to] = $this->resolveRange($request);
        $reporting = app(ReportingService::class);

        [$title, $filename, $sections] = match ($report) {
            'sales' => ['Sales Report', 'sales', $this->salesSections($reporting, $from, $to)],
            'inventory' => ['Inventory Report', 'inventory', $this->inventorySections($reporting, $from, $to)],
            'customers' => ['Customer Report', 'customers', $this->customerSections($reporting, $from, $to)],
            'operations' => ['Operations Report', 'operations', $this->operationsSections($reporting, $from, $to)],
            default => abort(404),
        };

        $filename .= '-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($title, $from, $to, $sections) {
            $handle = fopen('php://output', 'w');

            // UTF-8 byte-order mark so Excel opens names/accents correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            $this->writeRow($handle, ['CY-Market '.$title]);
            $this->writeRow($handle, ['Period', $from->format('d M Y').' - '.$to->format('d M Y')]);
            $this->writeRow($handle, ['Generated', now()->format('d M Y H:i')]);

            foreach ($sections as $sectionTitle => $rows) {
                $this->writeRow($handle, []);
                $this->writeRow($handle, [strtoupper($sectionTitle)]);

                foreach ($rows as $row) {
                    $this->writeRow($handle, $row);
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveRange(Request $request): array
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();

        // Same forgiving behaviour as the on-screen filter: a reversed
        // range is read as the same span the other way round.
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * Customer names, product names and notes are user-supplied, and a cell
     * starting with = + - or @ is executed as a formula by Excel/Sheets
     * (CSV injection). Prefixing a single quote forces it to plain text.
     * Numbers (including negative quantities) are left untouched.
     */
    protected function writeRow($handle, array $row): void
    {
        fputcsv($handle, array_map(function ($cell) {
            if (is_string($cell) && $cell !== '' && ! is_numeric($cell) && str_contains("=+-@\t\r", $cell[0])) {
                return "'".$cell;
            }

            return $cell;
        }, $row));
    }

    protected function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    // -----------------------------------------------------------------
    // Sales: summary, by period, by product, by category, by location
    // -----------------------------------------------------------------

    protected function salesSections(ReportingService $reporting, Carbon $from, Carbon $to): array
    {
        $summary = $reporting->salesSummary($from, $to);

        $byProduct = [['Product', 'SKU', 'Units Sold', 'Revenue (GHS)']];
        foreach ($reporting->salesByProduct($from, $to, self::ROW_LIMIT) as $row) {
            $byProduct[] = [$row->product, $row->sku, $row->units_sold, $this->money($row->revenue)];
        }

        $byCategory = [['Category', 'Units Sold', 'Revenue (GHS)']];
        foreach ($reporting->salesByCategory($from, $to) as $row) {
            $byCategory[] = [$row->category, $row->units, $this->money($row->revenue)];
        }

        $byLocation = [['Delivery Zone', 'Orders', 'Revenue (GHS)']];
        foreach ($reporting->salesByLocation($from, $to) as $row) {
            $byLocation[] = [$row->location, $row->orders, $this->money($row->revenue)];
        }

        $byDay = [['Date', 'Orders', 'Revenue (GHS)']];
        foreach ($reporting->salesByPeriod($from, $to) as $row) {
            $byDay[] = [$row->day, $row->orders, $this->money($row->revenue)];
        }

        return [
            'Revenue summary' => [
                ['Metric', 'Value'],
                ['Paid orders', $summary['order_count']],
                ['Gross revenue (GHS)', $this->money($summary['gross_revenue'])],
                ['Refunded (GHS)', $this->money($summary['refunded_amount'])],
                ['Net revenue (GHS)', $this->money($summary['net_revenue'])],
                ['Delivery fees (GHS)', $this->money($summary['delivery_fees'])],
                ['Average order value (GHS)', $this->money($summary['average_order_value'])],
            ],
            'Sales by day' => $byDay,
            'Sales by product' => $byProduct,
            'Sales by category' => $byCategory,
            'Sales by location' => $byLocation,
        ];
    }

    // -----------------------------------------------------------------
    // Inventory: valuation, stock levels, low stock, write-offs, movements
    // -----------------------------------------------------------------

    protected function inventorySections(ReportingService $reporting, Carbon $from, Carbon $to): array
    {
        $summary = $reporting->inventoryReport();

        $levels = [['SKU', 'Product', 'Category', 'Unit', 'Available Qty', 'Reserved Qty', 'Sellable Qty', 'Low Stock Threshold', 'Status', 'Unit Price (GHS)', 'Stock Value (GHS)']];
        foreach ($reporting->stockLevels() as $product) {
            $levels[] = [
                $product->sku,
                $product->name,
                $product->category?->name,
                $product->unit_of_measurement,
                $product->available_quantity,
                $product->reserved_quantity,
                $product->sellable_quantity,
                $product->low_stock_threshold ?? config('cymarket.default_low_stock_threshold'),
                $product->is_out_of_stock ? 'Out of stock' : ($product->is_low_stock ? 'Low stock' : 'OK'),
                $this->money($product->selling_price),
                $this->money($product->available_quantity * $product->selling_price),
            ];
        }

        $writeOffs = [['Date', 'Product', 'Quantity Removed', 'Reason']];
        foreach ($reporting->spoilageWriteOffs($from, $to, self::ROW_LIMIT) as $movement) {
            $writeOffs[] = [$movement->created_at->format('Y-m-d H:i'), $movement->product?->name ?? 'Deleted product', $movement->quantity, $movement->note];
        }

        $movements = [['Date', 'Product', 'SKU', 'Type', 'Quantity', 'Reason', 'By']];
        foreach ($reporting->stockMovementHistory($from, $to, self::ROW_LIMIT) as $movement) {
            $movements[] = [
                $movement->created_at->format('Y-m-d H:i'),
                $movement->product?->name ?? 'Deleted product',
                $movement->product?->sku,
                $movement->type->label(),
                $movement->quantity,
                $movement->note,
                $movement->user?->name ?? 'System',
            ];
        }

        return [
            'Inventory summary' => [
                ['Metric', 'Value'],
                ['Total SKUs', $summary['total_skus']],
                ['Out of stock', $summary['out_of_stock']],
                ['Low stock', $summary['low_stock']],
                ['Inventory valuation at selling price (GHS)', $this->money($summary['inventory_valuation'])],
            ],
            'Current stock levels' => $levels,
            'Spoilage and write-offs' => $writeOffs,
            'Stock movement history' => $movements,
        ];
    }

    // -----------------------------------------------------------------
    // Customers: new vs returning, most active, lifetime value
    // -----------------------------------------------------------------

    protected function customerSections(ReportingService $reporting, Carbon $from, Carbon $to): array
    {
        $summary = $reporting->customerReport($from, $to);

        $mostActive = [['Customer', 'Phone', 'Orders', 'Spent (GHS)']];
        foreach ($reporting->mostActiveCustomers($from, $to, 1000) as $row) {
            $mostActive[] = [$row->name, $row->phone, $row->order_count, $this->money($row->total_spent)];
        }

        $lifetime = [['Customer', 'Phone', 'Lifetime Orders', 'Lifetime Value (GHS)', 'Average Order Value (GHS)']];
        foreach ($reporting->customerLifetimeValue(1000) as $row) {
            $lifetime[] = [$row->name, $row->phone, $row->lifetime_orders, $this->money($row->lifetime_value), $this->money($row->average_order_value)];
        }

        return [
            'New vs returning' => [
                ['Metric', 'Value'],
                ['New accounts registered', $summary['new_customers']],
                ['First-time buyers', $summary['first_time_buyers']],
                ['Returning buyers', $summary['returning_customers']],
            ],
            'Most active customers (period)' => $mostActive,
            'Customer lifetime value (all-time)' => $lifetime,
        ];
    }

    // -----------------------------------------------------------------
    // Operations: orders by status, gross/net revenue, fulfilment metrics
    // -----------------------------------------------------------------

    protected function operationsSections(ReportingService $reporting, Carbon $from, Carbon $to): array
    {
        $ops = $reporting->operationsReport($from, $to);
        $fulfilment = $reporting->fulfilmentPerformance($from, $to);

        $statuses = [['Status', 'Orders']];
        foreach (OrderStatus::cases() as $status) {
            $statuses[] = [$status->label(), $ops[$status->value] ?? 0];
        }

        $hours = fn ($value) => $value ?? 'n/a';

        return [
            'Orders by status' => $statuses,
            'Revenue' => [
                ['Metric', 'Value (GHS)'],
                ['Gross revenue', $this->money($ops['gross_revenue'])],
                ['Refunded', $this->money($ops['refunded_amount'])],
                ['Net revenue (net of refunds)', $this->money($ops['net_revenue'])],
            ],
            'Fulfilment performance' => [
                ['Metric', 'Value'],
                ['Average hours placed to paid', $hours($fulfilment['avg_hours_placed_to_paid'])],
                ['Average hours paid to dispatched', $hours($fulfilment['avg_hours_paid_to_dispatched'])],
                ['Average hours dispatched to delivered', $hours($fulfilment['avg_hours_dispatched_to_delivered'])],
                ['Average hours placed to delivered', $hours($fulfilment['avg_hours_placed_to_delivered'])],
                ['Total orders', $fulfilment['total_orders']],
                ['Cancelled orders', $fulfilment['cancelled_orders']],
                ['Cancellation rate (%)', $fulfilment['cancellation_rate']],
            ],
        ];
    }
}

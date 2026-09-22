<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reporting & Management Dashboard queries (TOR §6.11). Feeds the Filament
 * admin widgets and the Reports page; every method accepts an optional date
 * range so the admin can apply daily/weekly/monthly (or custom) filters as
 * required by:
 *
 *  Sales       - by period, by product, by category, by location, revenue totals.
 *  Inventory   - current stock levels, low-stock alerts, spoilage/write-offs,
 *                stock movement history, inventory valuation.
 *  Customers   - new vs returning, most active, basic lifetime-value indicators.
 *  Operations  - orders by status, gross and net-of-refunds revenue,
 *                fulfilment performance metrics.
 */
class ReportingService
{
    protected function paidStatuses(): array
    {
        return [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Dispatched, OrderStatus::Delivered, OrderStatus::Completed];
    }

    // -----------------------------------------------------------------
    // Sales
    // -----------------------------------------------------------------

    /**
     * Revenue totals for a period.
     *
     *  - net_revenue      : orders currently in a paid status (Paid ->
     *                       Completed) - money the business kept.
     *  - refunded_amount  : orders cancelled AFTER a successful payment
     *                       (OrderService::cancel() leaves the payment
     *                       row Successful and logs a refund obligation).
     *  - gross_revenue    : everything collected = net + refunded.
     *
     * Cancelled orders are not in a paid status, so they are never part of
     * net_revenue; gross must add them back rather than net subtracting
     * them a second time.
     */
    public function salesSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = Order::query()->whereIn('status', $this->paidStatuses());

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $netRevenue = (float) (clone $query)->sum('total');

        $refundedAmount = (float) Order::where('status', OrderStatus::Cancelled)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->whereHas('payments', fn ($q) => $q->where('status', PaymentStatus::Successful))
            ->sum('total');

        return [
            'order_count' => (clone $query)->count(),
            'gross_revenue' => $netRevenue + $refundedAmount,
            'refunded_amount' => $refundedAmount,
            'net_revenue' => $netRevenue,
            'delivery_fees' => (float) (clone $query)->sum('delivery_fee'),
            'average_order_value' => (float) (clone $query)->avg('total'),
        ];
    }

    /**
     * Sales by period (TOR §6.11 "Sales - by period"): one row per day
     * inside the selected range, including days with no orders so the
     * trend has no gaps.
     *
     * @return Collection<int, object{day: string, orders: int, revenue: float}>
     */
    public function salesByPeriod(Carbon $from, Carbon $to): Collection
    {
        // "This Month" runs to month-end; days that haven't happened yet
        // are noise, so the daily series stops at today.
        $to = $to->copy()->min(now()->endOfDay());

        $rows = Order::query()
            ->whereIn('status', $this->paidStatuses())
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->selectRaw('date(created_at) as day, count(*) as orders, sum(total) as revenue')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $days = collect();
        for ($cursor = $from->copy()->startOfDay(); $cursor->lte($to); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $days->push((object) [
                'day' => $key,
                'orders' => (int) ($rows[$key]->orders ?? 0),
                'revenue' => (float) ($rows[$key]->revenue ?? 0),
            ]);
        }

        return $days;
    }

    public function salesByDay(int $days = 14): Collection
    {
        return Order::query()
            ->whereIn('status', $this->paidStatuses())
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->selectRaw('date(created_at) as day, count(*) as orders, sum(total) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    public function salesByCategory(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('orders.status', array_map(fn ($s) => $s->value, $this->paidStatuses()))
            ->when($from, fn ($q) => $q->where('orders.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('orders.created_at', '<=', $to))
            ->selectRaw('categories.name as category, sum(order_items.line_total) as revenue, sum(order_items.quantity) as units')
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->get();
    }

    /**
     * Sales by product for a period (TOR §6.11 "Sales - by product").
     * Distinct from topProducts() below, which is an all-time ranking used
     * by the dashboard widget.
     */
    public function salesByProduct(?Carbon $from = null, ?Carbon $to = null, int $limit = 50): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', array_map(fn ($s) => $s->value, $this->paidStatuses()))
            ->when($from, fn ($q) => $q->where('orders.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('orders.created_at', '<=', $to))
            ->selectRaw('order_items.product_name as product, order_items.sku, sum(order_items.quantity) as units_sold, sum(order_items.line_total) as revenue')
            ->groupBy('order_items.product_name', 'order_items.sku')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /**
     * Sales by delivery location/zone (TOR §6.11 "Sales - by location").
     */
    public function salesByLocation(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return DB::table('orders')
            ->leftJoin('delivery_zones', 'delivery_zones.id', '=', 'orders.delivery_zone_id')
            ->whereIn('orders.status', array_map(fn ($s) => $s->value, $this->paidStatuses()))
            ->when($from, fn ($q) => $q->where('orders.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('orders.created_at', '<=', $to))
            ->selectRaw("COALESCE(delivery_zones.name, 'Unassigned') as location, count(*) as orders, sum(orders.total) as revenue")
            ->groupBy('location')
            ->orderByDesc('revenue')
            ->get();
    }

    public function topProducts(int $limit = 10): Collection
    {
        return Product::query()
            ->orderByDesc('sold_count')
            ->limit($limit)
            ->get(['id', 'name', 'sold_count', 'selling_price']);
    }

    // -----------------------------------------------------------------
    // Inventory
    // -----------------------------------------------------------------

    public function inventoryReport(): array
    {
        return [
            'total_skus' => Product::count(),
            'out_of_stock' => Product::whereColumn('available_quantity', '<=', 'reserved_quantity')->count(),
            'low_stock' => Product::query()
                ->whereColumn('available_quantity', '>', 'reserved_quantity')
                ->whereRaw('(available_quantity - reserved_quantity) <= COALESCE(low_stock_threshold, ?)', [config('cymarket.default_low_stock_threshold')])
                ->count(),
            'inventory_valuation' => (float) Product::query()->selectRaw('SUM(available_quantity * selling_price) as v')->value('v'),
        ];
    }

    /**
     * Current stock levels (TOR §6.11 "Inventory - current stock levels"),
     * lowest sellable quantity first so the items needing attention lead.
     * Pass null for the full catalogue (CSV export).
     */
    public function stockLevels(?int $limit = null): Collection
    {
        return Product::query()
            ->with('category:id,name')
            ->orderByRaw('(available_quantity - reserved_quantity) asc')
            ->orderBy('name')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();
    }

    /**
     * Stock valuation grouped by category, for the inventory report's
     * chart. Always current - there is no "period" for stock on hand.
     */
    public function inventoryValuationByCategory(): Collection
    {
        return DB::table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw('categories.name as category, SUM(products.available_quantity * products.selling_price) as valuation')
            ->groupBy('categories.name')
            ->orderByDesc('valuation')
            ->get();
    }

    public function lowStockProducts(int $limit = 20): Collection
    {
        return Product::query()
            ->active()
            ->whereColumn('available_quantity', '>', 'reserved_quantity')
            ->whereRaw('(available_quantity - reserved_quantity) <= COALESCE(low_stock_threshold, ?)', [config('cymarket.default_low_stock_threshold')])
            ->orderByRaw('(available_quantity - reserved_quantity) asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Spoilage / write-offs (TOR §6.11 "Inventory - spoilage/write-offs").
     * Manual negative stock adjustments made via StockService::manualAdjustment()
     * are tagged StockMovementType::Adjustment with quantity stored as
     * abs($delta), so this simply surfaces that slice of the audit trail.
     */
    public function spoilageWriteOffs(?Carbon $from = null, ?Carbon $to = null, int $limit = 50): Collection
    {
        return StockMovement::query()
            ->with('product:id,name,sku')
            ->where('type', StockMovementType::Adjustment)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest()
            ->limit($limit)
            ->get(['id', 'product_id', 'quantity', 'note', 'user_id', 'created_at']);
    }

    /**
     * Stock movement history (TOR §6.11 "Inventory - stock movement history").
     */
    public function stockMovementHistory(?Carbon $from = null, ?Carbon $to = null, int $limit = 100): Collection
    {
        return StockMovement::query()
            ->with(['product:id,name,sku', 'user:id,name'])
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest()
            ->limit($limit)
            ->get();
    }

    // -----------------------------------------------------------------
    // Customers
    // -----------------------------------------------------------------

    /**
     * New vs returning customers (TOR §6.11), all scoped to the period:
     *
     *  - new_customers       : accounts registered in the period.
     *  - first_time_buyers   : customers whose FIRST paid order falls in
     *                          the period.
     *  - returning_customers : customers who paid for an order in the
     *                          period AND already had a paid order before
     *                          the period started.
     *
     * With no `$from` there is no "before the period", so returning falls
     * back to customers with more than one paid order overall.
     */
    public function customerReport(?Carbon $from = null, ?Carbon $to = null): array
    {
        $newCustomers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', config('cymarket.roles.customer')))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->count();

        $paid = array_map(fn ($s) => $s->value, $this->paidStatuses());

        $firstOrders = DB::table('orders')
            ->whereIn('status', $paid)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, min(created_at) as first_order_at, count(*) as order_total');

        $firstTimeBuyers = DB::query()
            ->fromSub($firstOrders, 'f')
            ->when($from, fn ($q) => $q->where('first_order_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('first_order_at', '<=', $to))
            ->count();

        if ($from) {
            $returningCustomers = DB::table('orders')
                ->joinSub($firstOrders, 'f', 'f.user_id', '=', 'orders.user_id')
                ->whereIn('orders.status', $paid)
                ->where('orders.created_at', '>=', $from)
                ->when($to, fn ($q) => $q->where('orders.created_at', '<=', $to))
                ->where('f.first_order_at', '<', $from)
                ->distinct()
                ->count('orders.user_id');
        } else {
            $returningCustomers = DB::query()->fromSub($firstOrders, 'f')->where('order_total', '>', 1)->count();
        }

        return [
            'new_customers' => $newCustomers,
            'first_time_buyers' => $firstTimeBuyers,
            'returning_customers' => $returningCustomers,
        ];
    }

    /**
     * Most active customers by order count in a period
     * (TOR §6.11 "Customers - most active").
     */
    public function mostActiveCustomers(?Carbon $from = null, ?Carbon $to = null, int $limit = 10): Collection
    {
        return DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->whereIn('orders.status', array_map(fn ($s) => $s->value, $this->paidStatuses()))
            ->when($from, fn ($q) => $q->where('orders.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('orders.created_at', '<=', $to))
            ->selectRaw('users.id, users.name, users.phone, count(*) as order_count, sum(orders.total) as total_spent')
            ->groupBy('users.id', 'users.name', 'users.phone')
            ->orderByDesc('order_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Basic lifetime-value indicators, all-time (TOR §6.11 "Customers -
     * basic lifetime-value indicators").
     */
    public function customerLifetimeValue(int $limit = 10): Collection
    {
        return DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->whereIn('orders.status', array_map(fn ($s) => $s->value, $this->paidStatuses()))
            ->selectRaw('users.id, users.name, users.phone, count(*) as lifetime_orders, sum(orders.total) as lifetime_value, avg(orders.total) as average_order_value')
            ->groupBy('users.id', 'users.name', 'users.phone')
            ->orderByDesc('lifetime_value')
            ->limit($limit)
            ->get();
    }

    // -----------------------------------------------------------------
    // Operations
    // -----------------------------------------------------------------

    public function operationsReport(?Carbon $from = null, ?Carbon $to = null): array
    {
        // Raw query builder (not Eloquent) so the grouped "status" column
        // comes back as a plain string, not cast through the OrderStatus
        // enum - keeping the lookup below a simple string comparison.
        $counts = DB::table('orders')
            ->selectRaw('status, count(*) as total')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusCounts = collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->toArray();

        $summary = $this->salesSummary($from, $to);

        return array_merge($statusCounts, [
            'gross_revenue' => $summary['gross_revenue'],
            'net_revenue' => $summary['net_revenue'],
            'refunded_amount' => $summary['refunded_amount'],
        ]);
    }

    /**
     * Fulfilment performance metrics (TOR §6.11 "Operations - fulfilment
     * performance metrics"): average hand-off times through the order
     * lifecycle timestamps already recorded on Order, plus cancellation
     * rate for the period.
     */
    public function fulfilmentPerformance(?Carbon $from = null, ?Carbon $to = null): array
    {
        $base = Order::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $totalOrders = (clone $base)->count();
        $cancelledOrders = (clone $base)->where('status', OrderStatus::Cancelled)->count();

        // Averaged in PHP (rather than a DB-specific TIMESTAMPDIFF/julianday
        // expression) so this works identically on the app's MySQL database
        // and the test suite's SQLite connection.
        $avgHours = function (string $startColumn, string $endColumn) use ($base) {
            $pairs = (clone $base)
                ->whereNotNull($startColumn)
                ->whereNotNull($endColumn)
                ->get([$startColumn, $endColumn]);

            if ($pairs->isEmpty()) {
                return null;
            }

            $totalMinutes = $pairs->sum(fn ($order) => abs($order->{$endColumn}->diffInMinutes($order->{$startColumn})));

            return round(($totalMinutes / $pairs->count()) / 60, 1);
        };

        return [
            'avg_hours_placed_to_paid' => $avgHours('placed_at', 'paid_at'),
            'avg_hours_paid_to_dispatched' => $avgHours('paid_at', 'dispatched_at'),
            'avg_hours_dispatched_to_delivered' => $avgHours('dispatched_at', 'delivered_at'),
            'avg_hours_placed_to_delivered' => $avgHours('placed_at', 'delivered_at'),
            'total_orders' => $totalOrders,
            'cancelled_orders' => $cancelledOrders,
            'cancellation_rate' => $totalOrders > 0 ? round(($cancelledOrders / $totalOrders) * 100, 1) : 0.0,
        ];
    }
}

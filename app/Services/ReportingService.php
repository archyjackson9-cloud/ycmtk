<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reporting & Management Dashboard queries (TOR §6.11). Feeds the Filament
 * admin widgets/pages; every method accepts an optional date range so the
 * admin can apply daily/weekly/monthly filters as required.
 */
class ReportingService
{
    protected function paidStatuses(): array
    {
        return [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Dispatched, OrderStatus::Delivered, OrderStatus::Completed];
    }

    public function salesSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = Order::query()->whereIn('status', $this->paidStatuses());

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return [
            'order_count' => (clone $query)->count(),
            'gross_revenue' => (float) (clone $query)->sum('total'),
            'delivery_fees' => (float) (clone $query)->sum('delivery_fee'),
            'average_order_value' => (float) (clone $query)->avg('total'),
            'refunded_amount' => (float) Order::where('status', OrderStatus::Cancelled)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->whereHas('payments', fn ($q) => $q->where('status', PaymentStatus::Successful))
                ->sum('total'),
        ];
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

    public function topProducts(int $limit = 10): Collection
    {
        return Product::query()
            ->orderByDesc('sold_count')
            ->limit($limit)
            ->get(['id', 'name', 'sold_count', 'selling_price']);
    }

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

    public function customerReport(?Carbon $from = null, ?Carbon $to = null): array
    {
        $newCustomers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', config('cymarket.roles.customer')))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->count();

        $returningCustomers = Order::query()
            ->whereIn('status', $this->paidStatuses())
            ->whereNotNull('user_id')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->count();

        return [
            'new_customers' => $newCustomers,
            'returning_customers' => $returningCustomers,
        ];
    }

    public function operationsReport(): array
    {
        // Raw query builder (not Eloquent) so the grouped "status" column
        // comes back as a plain string, not cast through the OrderStatus
        // enum - keeping the lookup below a simple string comparison.
        $counts = DB::table('orders')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->toArray();
    }
}

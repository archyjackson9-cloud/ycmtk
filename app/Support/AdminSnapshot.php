<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Cheap, briefly-cached numbers for the admin panel chrome (navigation
 * badges, dashboard KPIs, order tabs). One small set of queries is shared
 * by every widget and badge on a page instead of each re-running its own
 * (audit A-01/A-06). Read-only: all business rules stay in the services and
 * enums; this only counts. Call flush() after staff actions so badges do
 * not lag behind what the user just did.
 */
class AdminSnapshot
{
    private const COUNTS_KEY = 'admin.snapshot.counts';

    private const SALES_KEY = 'admin.snapshot.sales';

    private const COUNTS_TTL = 30;

    private const SALES_TTL = 60;

    /** Statuses a rider/packer still has work to do on for a scheduled delivery. */
    public const DELIVERY_OPEN_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Processing,
        OrderStatus::Dispatched,
        OrderStatus::OnHold,
    ];

    /** Orders staff must act on: confirmed-and-waiting, or held for a decision. */
    public const NEEDS_ACTION_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::OnHold,
    ];

    /**
     * @return array{
     *     by_status: array<string,int>, needs_action: int, pending_transfers: int,
     *     deliveries_today: int, low_stock: int, out_of_stock: int
     * }
     */
    public static function counts(): array
    {
        return Cache::remember(self::COUNTS_KEY, self::COUNTS_TTL, function (): array {
            $byStatus = Order::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn ($n) => (int) $n)
                ->all();

            $inventory = app(ReportingService::class)->inventoryReport();

            return [
                'by_status' => $byStatus,
                'needs_action' => collect(self::NEEDS_ACTION_STATUSES)->sum(fn (OrderStatus $s) => $byStatus[$s->value] ?? 0),
                'pending_transfers' => self::pendingTransfersQuery()->count(),
                'deliveries_today' => self::deliveriesTodayQuery()->count(),
                'low_stock' => (int) $inventory['low_stock'],
                'out_of_stock' => (int) $inventory['out_of_stock'],
            ];
        });
    }

    /**
     * Net sales for today / this week / this month (Super Admin KPIs).
     *
     * @return array{today: array, week: array, month: array}
     */
    public static function sales(): array
    {
        return Cache::remember(self::SALES_KEY, self::SALES_TTL, function (): array {
            $reporting = app(ReportingService::class);

            return [
                'today' => $reporting->salesSummary(now()->startOfDay(), now()->endOfDay()),
                'week' => $reporting->salesSummary(now()->startOfWeek(), now()->endOfWeek()),
                'month' => $reporting->salesSummary(now()->startOfMonth(), now()->endOfMonth()),
            ];
        });
    }

    public static function flush(): void
    {
        Cache::forget(self::COUNTS_KEY);
        Cache::forget(self::SALES_KEY);
    }

    /** Manual (bank/MoMo transfer) payments waiting for a human to confirm. */
    public static function pendingTransfersQuery(): Builder
    {
        return Payment::query()
            ->where('provider', 'manual')
            ->where('status', PaymentStatus::Pending);
    }

    /** Open orders whose scheduled delivery falls today. */
    public static function deliveriesTodayQuery(?Carbon $day = null): Builder
    {
        return self::applyDeliveringToday(Order::query(), $day);
    }

    /** Same rule applied to an existing order query (used by the "Today" tab). */
    public static function applyDeliveringToday(Builder $query, ?Carbon $day = null): Builder
    {
        $day ??= now();

        return $query
            ->whereIn('status', self::DELIVERY_OPEN_STATUSES)
            ->where('scheduled_delivery_at', '>=', $day->copy()->startOfDay())
            ->where('scheduled_delivery_at', '<=', $day->copy()->endOfDay());
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * TOR §11 "Inventory Officer offline / delayed status update -> Customer
 * still sees last known status. Escalation alerts to Super Admin after a
 * configurable time." Logged (and, once an internal_alert_phone setting is
 * configured, texted) rather than blocking the customer-facing flow.
 */
class EscalateStaleOrders extends Command
{
    protected $signature = 'cymarket:escalate-stale-orders';

    protected $description = 'Flag paid/processing orders that have not advanced within the configurable escalation window.';

    public function handle(SettingsService $settings): int
    {
        $hours = $settings->orderEscalationHours();

        $stale = Order::whereIn('status', [OrderStatus::Paid, OrderStatus::Processing])
            ->where('updated_at', '<', now()->subHours($hours))
            ->get(['id', 'order_number', 'status', 'updated_at']);

        foreach ($stale as $order) {
            Log::warning("Order {$order->order_number} has not advanced past {$order->status->label()} in over {$hours}h - escalate to Super Admin.", [
                'order_id' => $order->id,
            ]);
        }

        $this->info("Flagged {$stale->count()} stale order(s) for escalation.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Services\SettingsService;
use Illuminate\Console\Command;

/**
 * TOR §11 "Customer abandons checkout after selecting delivery -> Cart
 * remains available for a limited, configurable time (e.g., 24-48h)."
 * After that window, the cart is simply flagged abandoned for reporting -
 * its items are left intact in case the customer returns.
 */
class MarkAbandonedCarts extends Command
{
    protected $signature = 'cymarket:mark-abandoned-carts';

    protected $description = 'Flag active carts that have been inactive beyond the configurable abandoned-cart window.';

    public function handle(SettingsService $settings): int
    {
        $hours = $settings->abandonedCartHours();

        $count = Cart::where('status', 'active')
            ->where('updated_at', '<', now()->subHours($hours))
            ->update(['status' => 'abandoned']);

        $this->info("Marked {$count} cart(s) as abandoned (inactive > {$hours}h).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;

/**
 * TOR §11 soft stock-reservation safeguard: releases reservations whose
 * short "Payment Initiated" window has lapsed and cancels the still-unpaid
 * order that held them.
 */
class ReleaseExpiredStockReservations extends Command
{
    protected $signature = 'cymarket:release-expired-reservations';

    protected $description = 'Release stock reservations whose payment window has expired and cancel the affected pending-payment orders.';

    public function handle(StockService $stock): int
    {
        $released = $stock->releaseExpiredReservations();

        $this->info("Released {$released} expired stock reservation(s).");

        return self::SUCCESS;
    }
}

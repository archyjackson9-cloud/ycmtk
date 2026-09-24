<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\MtnMomoPaymentService;
use Illuminate\Console\Command;

/**
 * TOR §11 "System downtime during payment -> reconciliation job matches
 * payments to orders": polls MTN MoMo for any request still pending after
 * a minute (missed/never-sent callback) and expires ones MTN never resolves.
 */
class ReconcileMomoPayments extends Command
{
    protected $signature = 'cymarket:reconcile-momo-payments';

    protected $description = 'Check MTN MoMo for pending payments and resolve or expire them.';

    public function handle(MtnMomoPaymentService $momo): int
    {
        if (config('momo.mode') !== 'live') {
            return self::SUCCESS;
        }

        $count = 0;

        Payment::where('provider', 'mtn_momo')
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '<', now()->subMinute())
            ->each(function (Payment $payment) use ($momo, &$count) {
                $momo->expireStale($payment);
                $count++;
            });

        $this->info("Checked {$count} pending MoMo payment(s).");

        return self::SUCCESS;
    }
}

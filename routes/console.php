<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// TOR §11 Fallbacks, Edge Cases & Error Handling - the background jobs that
// keep stock, carts and stale orders honest without customer intervention.
Schedule::command('cymarket:release-expired-reservations')->everyMinute()->withoutOverlapping();
Schedule::command('cymarket:mark-abandoned-carts')->hourly()->withoutOverlapping();
Schedule::command('cymarket:escalate-stale-orders')->hourly()->withoutOverlapping();

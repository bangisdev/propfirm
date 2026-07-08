<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled tasks — grows with each phase (evaluation breach checks, order
// expiry sweeps, etc. land here as those modules are built).
Schedule::command('orders:expire-stale')->everyFiveMinutes();

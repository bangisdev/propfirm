<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled tasks.
Schedule::command('orders:expire-stale')->everyFiveMinutes();

// Rule engine: pulls fresh MT5 state and evaluates breach/phase-advancement rules.
// Every 5 minutes balances responsiveness (a breached account should be disabled
// promptly) against load on the MT5 bridge and broker infrastructure.
Schedule::command('trading-accounts:sync')->everyFiveMinutes()->withoutOverlapping();

// Daily drawdown baseline reset — runs at broker platform midnight. Adjust the
// timezone to match your broker's server time (commonly EET/EEST), NOT UTC,
// since that's what "daily" means for the drawdown rule in practice.
Schedule::command('trading-accounts:reset-daily-baseline')
    ->dailyAt('00:00')
    ->timezone('Europe/Bucharest');

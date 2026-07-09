<?php

namespace App\Console\Commands;

use App\Models\TradingAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDailyDrawdownBaseline extends Command
{
    protected $signature = 'trading-accounts:reset-daily-baseline';
    protected $description = "Resets each active account's day_start_balance to today's balance, so daily drawdown is measured fresh each day.";

    public function handle(): int
    {
        $today = now()->toDateString();

        $count = TradingAccount::where('status', 'active')
            ->whereNotNull('mt5_login')
            ->update([
                // Uses current_balance as the new floor reference — intentionally NOT
                // current_equity, since floating (unrealized) P&L shouldn't determine
                // tomorrow's baseline, only closed/realized balance.
                'day_start_balance' => DB::raw('current_balance'),
                'day_start_date' => $today,
            ]);

        $this->info("Reset daily drawdown baseline for {$count} active account(s).");

        return self::SUCCESS;
    }
}

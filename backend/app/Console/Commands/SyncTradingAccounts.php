<?php

namespace App\Console\Commands;

use App\Models\TradingAccount;
use App\Services\TradingRules\TradingAccountSyncService;
use Illuminate\Console\Command;

class SyncTradingAccounts extends Command
{
    protected $signature = 'trading-accounts:sync';
    protected $description = 'Fetches fresh MT5 state for every active trading account and runs the rule engine against it.';

    public function handle(TradingAccountSyncService $syncService): int
    {
        $accounts = TradingAccount::where('status', 'active')
            ->whereNotNull('mt5_login')
            ->with('challenge', 'user')
            ->get();

        $this->info("Syncing {$accounts->count()} active trading account(s)...");

        $breaches = 0;
        $advancements = 0;

        foreach ($accounts as $account) {
            $outcome = $syncService->sync($account);

            if ($outcome->isBreach()) {
                $breaches++;
            } elseif ($outcome->isAdvancement()) {
                $advancements++;
            }
        }

        $this->info("Done. Breaches: {$breaches}, Phase advancements: {$advancements}.");

        return self::SUCCESS;
    }
}

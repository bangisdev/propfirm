<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class ExpireStaleOrders extends Command
{
    protected $signature = 'orders:expire-stale';
    protected $description = 'Marks pending orders past their expiry as expired, freeing the trader to check out again.';

    public function handle(): int
    {
        $count = Order::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        if ($count > 0) {
            $this->info("Expired {$count} stale pending order(s).");
        }

        return self::SUCCESS;
    }
}

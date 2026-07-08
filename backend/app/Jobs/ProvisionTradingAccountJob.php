<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\TradingAccount;
use App\Notifications\Payments\AccountProvisionedNotification;
use App\Services\MT5\MT5BridgeClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionTradingAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 300, 900]; // seconds, exponential-ish

    public function __construct(private readonly string $orderId) {}

    public function handle(MT5BridgeClientInterface $bridge): void
    {
        $order = Order::with(['user', 'challenge'])->find($this->orderId);

        if (! $order || $order->status !== 'paid') {
            Log::warning('Skipping MT5 provisioning: order not found or not paid', ['order_id' => $this->orderId]);

            return;
        }

        // Idempotency: if a trading account already exists for this order
        // (e.g. job retried after partially succeeding), don't create a duplicate.
        if ($order->tradingAccount()->exists()) {
            return;
        }

        $tradingAccount = TradingAccount::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'challenge_id' => $order->challenge_id,
            'phase' => 'evaluation_1',
            'status' => 'provisioning',
            'starting_balance' => $order->challenge->account_size,
            'highest_balance' => $order->challenge->account_size,
        ]);

        $mt5Account = $bridge->provisionAccount([
            'group' => 'evaluation\\phase1',
            'balance' => (float) $order->challenge->account_size,
            'leverage' => 100,
            'name' => $order->user->name,
            'email' => $order->user->email,
        ]);

        $tradingAccount->update([
            'mt5_login' => $mt5Account['login'],
            'mt5_password_encrypted' => $mt5Account['password'],
            'mt5_server' => $mt5Account['server'],
            'status' => 'active',
            'current_balance' => $order->challenge->account_size,
            'current_equity' => $order->challenge->account_size,
            'provisioned_at' => now(),
        ]);

        $order->user->notify(new AccountProvisionedNotification($tradingAccount));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('MT5 account provisioning permanently failed', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);

        Order::find($this->orderId)?->tradingAccount?->update([
            'status' => 'disabled',
            'breach_reason' => 'MT5 provisioning failed after retries — needs manual setup.',
        ]);
    }
}

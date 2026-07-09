<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table) {
            // Daily drawdown resets each trading day; these track "today's" baseline.
            $table->decimal('day_start_balance', 12, 2)->nullable()->after('highest_balance');
            $table->date('day_start_date')->nullable()->after('day_start_balance');

            // Each phase (evaluation_1 -> evaluation_2 -> funded) measures its own
            // profit target from the balance at the moment that phase began, not
            // from the account's original starting balance.
            $table->decimal('phase_start_balance', 12, 2)->nullable()->after('day_start_date');

            // Used to detect "a new trading day happened" for min_trading_days counting,
            // distinct from first_trade_date (which never changes once set).
            $table->date('last_activity_date')->nullable()->after('first_trade_date');
        });
    }

    public function down(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'day_start_balance', 'day_start_date', 'phase_start_balance', 'last_activity_date',
            ]);
        });
    }
};

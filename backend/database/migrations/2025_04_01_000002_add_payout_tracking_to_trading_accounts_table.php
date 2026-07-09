<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table) {
            // The balance as of the last paid payout (or the moment the account became
            // funded, if none yet) — available profit for the NEXT payout is always
            // current_balance - payout_baseline_balance, so each payout only pays out
            // profit earned since the previous one.
            $table->decimal('payout_baseline_balance', 12, 2)->nullable()->after('phase_start_balance');
            $table->timestamp('next_payout_eligible_at')->nullable()->after('payout_baseline_balance');
        });
    }

    public function down(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table) {
            $table->dropColumn(['payout_baseline_balance', 'next_payout_eligible_at']);
        });
    }
};

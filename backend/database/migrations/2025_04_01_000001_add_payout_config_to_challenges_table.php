<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->decimal('min_payout_amount', 10, 2)->default(50.00)->after('profit_split_pct');
            $table->unsignedInteger('payout_cycle_days')->default(14)->after('min_payout_amount');
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn(['min_payout_amount', 'payout_cycle_days']);
        });
    }
};

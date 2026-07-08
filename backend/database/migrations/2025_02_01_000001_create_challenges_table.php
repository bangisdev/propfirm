<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // e.g. "Two-Step Evaluation — $50,000"
            $table->string('slug')->unique();
            $table->unsignedInteger('phase_count')->default(2); // 1 or 2 step
            $table->decimal('account_size', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('price', 10, 2);

            // Rule parameters consumed by the trading rules engine (Phase 3)
            $table->decimal('profit_target_phase1_pct', 5, 2)->default(10.00);
            $table->decimal('profit_target_phase2_pct', 5, 2)->nullable(); // null for 1-step
            $table->decimal('max_daily_drawdown_pct', 5, 2)->default(5.00);
            $table->decimal('max_total_drawdown_pct', 5, 2)->default(10.00);
            $table->unsignedInteger('min_trading_days')->default(5);
            $table->decimal('profit_split_pct', 5, 2)->default(80.00);
            $table->boolean('news_trading_restricted')->default(true);
            $table->boolean('weekend_holding_allowed')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};

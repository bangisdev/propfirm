<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('order_id');
            $table->uuid('challenge_id');

            // MT5 identity — provisioned asynchronously by the MT5 bridge service.
            $table->unsignedBigInteger('mt5_login')->nullable()->unique();
            $table->text('mt5_password_encrypted')->nullable(); // investor/read-only password, encrypted at rest
            $table->string('mt5_server')->nullable();

            $table->enum('phase', ['evaluation_1', 'evaluation_2', 'funded'])->default('evaluation_1');
            $table->enum('status', [
                'provisioning', 'active', 'passed', 'failed', 'breached', 'funded', 'disabled',
            ])->default('provisioning');

            $table->decimal('starting_balance', 12, 2);
            $table->decimal('current_balance', 12, 2)->nullable();
            $table->decimal('current_equity', 12, 2)->nullable();
            $table->decimal('highest_balance', 12, 2)->nullable(); // for trailing/max drawdown calc

            $table->date('first_trade_date')->nullable();
            $table->unsignedInteger('trading_days_count')->default(0);

            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('breached_at')->nullable();
            $table->string('breach_reason')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('challenge_id')->references('id')->on('challenges')->restrictOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_accounts');
    }
};

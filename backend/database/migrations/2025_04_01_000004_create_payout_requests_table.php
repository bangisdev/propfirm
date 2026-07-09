<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trading_account_id');
            $table->uuid('user_id');
            $table->uuid('bank_account_id');

            $table->decimal('requested_amount', 12, 2); // total realized profit being withdrawn
            $table->decimal('profit_split_pct', 5, 2); // snapshot at request time, so later challenge edits don't retroactively change it
            $table->decimal('trader_amount', 12, 2); // what the trader actually receives
            $table->decimal('firm_amount', 12, 2); // the firm's retained share
            $table->string('currency', 3)->default('USD');

            $table->enum('status', ['pending', 'approved', 'rejected', 'processing', 'paid', 'failed'])
                ->default('pending');
            $table->text('admin_notes')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->string('paystack_transfer_code')->nullable();
            $table->string('paystack_reference')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->foreign('trading_account_id')->references('id')->on('trading_accounts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('payout_bank_accounts')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['trading_account_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};

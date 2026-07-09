<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('affiliate_user_id'); // the referrer who earns the commission
            $table->uuid('referred_user_id'); // the trader whose purchase generated it
            $table->uuid('order_id');

            $table->decimal('order_amount', 10, 2); // the referred order's total, for audit/display
            $table->decimal('commission_pct', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->string('currency', 3)->default('USD');

            $table->enum('status', ['pending', 'processing', 'paid', 'failed'])->default('pending');
            $table->string('paystack_transfer_code')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->foreign('affiliate_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('referred_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();

            // One commission per order — an order can only ever generate one
            // referral payout, even if retried/reprocessed.
            $table->unique('order_id');
            $table->index(['affiliate_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};

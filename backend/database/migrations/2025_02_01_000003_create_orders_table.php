<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('challenge_id');
            $table->uuid('coupon_id')->nullable();

            $table->string('reference', 40)->unique(); // our own idempotency key, sent to Paystack
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('USD');

            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded'])
                ->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at'); // unpaid orders expire — freed for re-purchase

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('challenge_id')->references('id')->on('challenges')->restrictOnDelete();
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

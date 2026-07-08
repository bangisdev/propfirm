<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('provider', 20)->default('paystack');
            $table->string('provider_reference')->unique(); // Paystack's own tx reference
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'success', 'failed', 'reversed'])->default('pending');
            $table->string('channel')->nullable(); // card, bank_transfer, ussd, etc.
            $table->json('gateway_response')->nullable(); // full verified payload, for audit/dispute resolution
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->index(['provider', 'status']);
        });

        // Every inbound webhook call is logged, keyed by Paystack's event id,
        // so a redelivered webhook (Paystack retries on non-2xx) is a no-op.
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 20)->default('paystack');
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
    }
};

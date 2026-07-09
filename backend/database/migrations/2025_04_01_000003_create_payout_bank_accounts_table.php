<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('bank_name');
            $table->string('bank_code', 10);
            $table->string('account_number', 20);
            $table->string('account_name'); // returned/confirmed by Paystack account resolution
            $table->string('currency', 3)->default('NGN');
            $table->string('paystack_recipient_code')->nullable()->unique(); // created lazily on first payout
            $table->boolean('is_default')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_bank_accounts');
    }
};

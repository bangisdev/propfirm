<?php

use App\Http\Controllers\Api\V1\Challenges\ChallengeController;
use App\Http\Controllers\Api\V1\Payments\CheckoutController;
use App\Http\Controllers\Api\V1\Payments\PaystackWebhookController;
use App\Http\Controllers\Api\V1\TradingAccounts\TradingAccountController;
use Illuminate\Support\Facades\Route;

// Public — pricing page.
Route::get('challenges', [ChallengeController::class, 'index']);
Route::get('challenges/{challenge}', [ChallengeController::class, 'show']);

// Paystack webhook — unauthenticated by design (Paystack calls this directly),
// integrity is enforced via HMAC signature verification inside the controller,
// and it's excluded from CSRF/session middleware since it's a pure JSON API route.
Route::post('webhooks/paystack', [PaystackWebhookController::class, 'handle'])
    ->middleware('throttle:120,1');

// Authenticated trader routes.
Route::middleware('auth:api')->group(function () {
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1');
    Route::get('checkout/{reference}', [CheckoutController::class, 'show']);
    Route::get('orders', [CheckoutController::class, 'index']);

    Route::get('trading-accounts', [TradingAccountController::class, 'index']);
    Route::get('trading-accounts/{tradingAccount}/credentials', [TradingAccountController::class, 'credentials']);
});

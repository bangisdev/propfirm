<?php

use App\Http\Controllers\Api\V1\Admin\PayoutReviewController;
use App\Http\Controllers\Api\V1\Payouts\PayoutController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('payout-bank-accounts', [PayoutController::class, 'bankAccounts']);
    Route::post('payout-bank-accounts', [PayoutController::class, 'storeBankAccount'])->middleware('throttle:10,1');

    Route::get('payout-requests', [PayoutController::class, 'index']);
    Route::post('payout-requests', [PayoutController::class, 'store'])->middleware('throttle:10,1');

    // Admin review — permission-gated both at the route (defense in depth) and
    // again inside the controller/request via $this->authorize(), since a route
    // middleware typo shouldn't be the only thing standing between a trader and
    // another trader's payout approval endpoint.
    Route::prefix('admin')->middleware('permission:withdrawals.approve')->group(function () {
        Route::get('payout-requests', [PayoutReviewController::class, 'index']);
        Route::post('payout-requests/{payoutRequest}/approve', [PayoutReviewController::class, 'approve']);
        Route::post('payout-requests/{payoutRequest}/reject', [PayoutReviewController::class, 'reject']);
    });
});

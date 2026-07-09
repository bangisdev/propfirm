<?php

use App\Http\Controllers\Api\V1\Admin\AffiliatePayoutController;
use App\Http\Controllers\Api\V1\Affiliate\AffiliateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('affiliate/stats', [AffiliateController::class, 'stats']);
    Route::get('affiliate/referrals', [AffiliateController::class, 'referrals']);
    Route::get('affiliate/commissions', [AffiliateController::class, 'commissions']);

    Route::post('admin/affiliates/{user}/payout', [AffiliatePayoutController::class, 'payout'])
        ->middleware('permission:withdrawals.approve');
});

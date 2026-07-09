<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:10,1');

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');

        Route::post('refresh', [AuthController::class, 'refresh'])
            ->middleware('throttle:30,1');

        Route::middleware('auth:api')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    // Phase 2+ route groups (challenges, payments, mt5, trading-rules, wallet,
    // affiliate, kyc, support) are registered in their own route files and
    // included here as those modules are built.
    require __DIR__.'/api/payments.php';
    require __DIR__.'/api/payouts.php';
});

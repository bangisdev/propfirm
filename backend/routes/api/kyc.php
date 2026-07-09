<?php

use App\Http\Controllers\Api\V1\Admin\KycReviewController;
use App\Http\Controllers\Api\V1\Kyc\KycController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('kyc', [KycController::class, 'show']);
    Route::post('kyc', [KycController::class, 'store'])->middleware('throttle:5,1');

    Route::prefix('admin')->middleware('permission:kyc.review')->group(function () {
        Route::get('kyc-submissions', [KycReviewController::class, 'index']);
        Route::post('kyc-submissions/{kycSubmission}/approve', [KycReviewController::class, 'approve']);
        Route::post('kyc-submissions/{kycSubmission}/reject', [KycReviewController::class, 'reject']);
    });
});

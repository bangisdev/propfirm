<?php

use App\Http\Controllers\Api\V1\Admin\SupportTicketAdminController;
use App\Http\Controllers\Api\V1\Support\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('support-tickets', [SupportTicketController::class, 'index']);
    Route::post('support-tickets', [SupportTicketController::class, 'store'])->middleware('throttle:10,1');
    Route::get('support-tickets/{supportTicket}', [SupportTicketController::class, 'show']);
    Route::post('support-tickets/{supportTicket}/reply', [SupportTicketController::class, 'reply']);

    Route::prefix('admin')->middleware('permission:tickets.manage')->group(function () {
        Route::get('support-tickets', [SupportTicketAdminController::class, 'index']);
        Route::get('support-tickets/{supportTicket}', [SupportTicketAdminController::class, 'show']);
        Route::post('support-tickets/{supportTicket}/assign', [SupportTicketAdminController::class, 'assign']);
        Route::patch('support-tickets/{supportTicket}/status', [SupportTicketAdminController::class, 'updateStatus']);
        Route::post('support-tickets/{supportTicket}/reply', [SupportTicketAdminController::class, 'reply']);
    });
});

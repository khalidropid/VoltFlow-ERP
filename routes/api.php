<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\SettlementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('idempotent');

        Route::middleware(['station.access', 'idempotent'])->group(function () {
            Route::post('/collections', [CollectionController::class, 'store']);
            Route::post('/billing/readings', [BillingController::class, 'reading']);
            Route::post('/billing/invoices', [BillingController::class, 'invoice']);
            Route::post('/collections/{payment}/void', [CollectionController::class, 'void']);
            Route::post('/collection-settlements', [SettlementController::class, 'store']);
            Route::post('/collection-settlements/{settlement}/void', [SettlementController::class, 'void']);
        });
    });
});

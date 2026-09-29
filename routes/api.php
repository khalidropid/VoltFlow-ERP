<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\CollectorLocationController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\SettlementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('idempotent');
        Route::post('/devices/register', [DeviceController::class, 'register'])->middleware('idempotent');
        Route::post('/integration/events', [IntegrationController::class, 'storeEvent'])->middleware('idempotent');

        Route::middleware(['station.access', 'idempotent'])->group(function () {
            Route::post('/collections', [CollectionController::class, 'store']);
            Route::post('/collections/{payment}/void', [CollectionController::class, 'void']);
            Route::post('/collection-settlements', [SettlementController::class, 'store']);
            Route::post('/collection-settlements/{settlement}/void', [SettlementController::class, 'void']);
            Route::post('/collector-locations', [CollectorLocationController::class, 'store']);
        });
    });
});

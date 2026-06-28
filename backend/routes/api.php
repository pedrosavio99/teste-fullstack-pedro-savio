<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\AffiliateController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);

Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/metrics', [OrderController::class, 'metrics']);
    Route::get('/{id}', [OrderController::class, 'show'])->whereNumber('id');
    Route::post('/{id}/status', [OrderController::class, 'updateStatus'])->whereNumber('id');
});

Route::get('/affiliates/{id}/summary', [AffiliateController::class, 'summary'])->whereNumber('id');
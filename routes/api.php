<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::post('/payments/bank-webhook', BankWebhookController::class)->middleware('throttle:60,1')->name('api.payments.bank-webhook');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:director')->prefix('director')->group(function () {
    });

    Route::middleware('role:sale_admin')->prefix('sale-admin')->group(function () {
    });

    Route::middleware('role:sale_leader')->prefix('sale-leader')->group(function () {
    });

    Route::middleware('role:salesperson')->prefix('salesperson')->group(function () {
    });

    Route::middleware('role:accountant')->prefix('accountant')->group(function () {
    });
});

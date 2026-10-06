<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Tất cả route trong file này có tiền tố /api

// ── Auth ──────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ── Director ─────────────────────────────────────────────────────
    Route::middleware('role:director')->prefix('director')->group(function () {
        // Placeholder – sẽ bổ sung sau
    });

    // ── Sale Admin ───────────────────────────────────────────────────
    Route::middleware('role:sale_admin')->prefix('sale-admin')->group(function () {
        // Placeholder – sẽ bổ sung sau
    });

    // ── Sale Leader ──────────────────────────────────────────────────
    Route::middleware('role:sale_leader')->prefix('sale-leader')->group(function () {
        // Placeholder – sẽ bổ sung sau
    });

    // ── Salesperson ──────────────────────────────────────────────────
    Route::middleware('role:salesperson')->prefix('salesperson')->group(function () {
        // Placeholder – sẽ bổ sung sau
    });

    // ── Accountant ───────────────────────────────────────────────────
    Route::middleware('role:accountant')->prefix('accountant')->group(function () {
        // Placeholder – sẽ bổ sung sau
    });
});

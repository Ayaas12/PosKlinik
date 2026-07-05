<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DrugController;
use App\Http\Controllers\Api\DrugUnitController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — POS Apotek
|--------------------------------------------------------------------------
| All routes use:
| - throttle:api middleware (rate limiting)
| - auth:sanctum for protected routes
| SECURITY: No endpoints accept credentials in URL parameters.
*/

// Public auth routes (rate limited aggressively)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/login',  [AuthController::class, 'login']);
});

// Protected routes
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Auth
    Route::post('/logout',          [AuthController::class, 'logout']);
    Route::get('/me',               [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Drugs – all authenticated users can read; write restricted by policy
    Route::get('/drugs/search', [DrugController::class, 'search']);
    Route::get('/drugs/{drug}/movements', [DrugController::class, 'movements']);
    Route::apiResource('/drugs', DrugController::class);
    Route::post('/drugs/{drug}/adjust-stock', [DrugController::class, 'adjustStock']);

    // Drug Unit Pricing (per-drug unit variants)
    Route::get('/drugs/{drug}/units',                     [DrugUnitController::class, 'index']);
    Route::post('/drugs/{drug}/units',                    [DrugUnitController::class, 'store']);
    Route::put('/drugs/{drug}/units/{unit}',              [DrugUnitController::class, 'update']);
    Route::delete('/drugs/{drug}/units/{unit}',           [DrugUnitController::class, 'destroy']);

    // Batch management
    Route::get('/batches/summary',        [BatchController::class, 'summary']);
    Route::get('/drugs/{drug}/batches',   [BatchController::class, 'byDrug']);
    Route::apiResource('/batches', BatchController::class)->except(['destroy']);

    // Categories & Suppliers
    Route::apiResource('/categories', CategoryController::class)->except(['show']);
    Route::apiResource('/suppliers',  SupplierController::class)->except(['show']);

    // Transactions (POS)
    Route::apiResource('/transactions', TransactionController::class)->only(['index', 'show', 'store']);
    Route::post('/transactions/{transaction}/cancel', [TransactionController::class, 'cancel']);

    // Reports (apoteker + admin)
    Route::prefix('reports')->group(function () {
        Route::get('/sales',           [ReportController::class, 'sales']);
        Route::get('/stock',           [ReportController::class, 'stock']);
        Route::get('/profit-loss',     [ReportController::class, 'profitLoss']);
        Route::get('/stock-movements', [ReportController::class, 'stockMovements']);
    });

    // User management (admin only)
    Route::apiResource('/users', UserController::class)->except(['show']);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
});

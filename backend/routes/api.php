<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BbAssignmentController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FranchiseController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn() => response()->json(['status' => 'ok']));

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes are prefixed with /api/v1 (configured in bootstrap/app.php).
| Public routes (no auth): login, register, assessments, BB applications.
| Protected routes require: auth:sanctum + role/permission middleware.
*/

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'data'    => null,
        'message' => 'SM Portal API is running',
    ]);
});

// ---------------------------------------------------------------------------
// Auth — public
// ---------------------------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});

// ---------------------------------------------------------------------------
// Auth — protected
// ---------------------------------------------------------------------------
Route::prefix('auth')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ---------------------------------------------------------------------------
// Protected resources — require Sanctum authentication
// ---------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('franchises', FranchiseController::class);

    // close-deal must be declared BEFORE apiResource to prevent {company}
    // from capturing the literal string "close-deal".
    Route::post('companies/close-deal', [CompanyController::class, 'closeDeal']);
    Route::apiResource('companies', CompanyController::class);

    Route::post('bb-assignments', [BbAssignmentController::class, 'store']);
    Route::delete('bb-assignments/{bbAssignment}', [BbAssignmentController::class, 'destroy']);

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/kpis', [DashboardController::class, 'kpis']);
        Route::get('/feed', [DashboardController::class, 'feed']);
        Route::get('/events', [DashboardController::class, 'events']);
        Route::get('/tracking', [DashboardController::class, 'tracking']);
        Route::get('/contracts', [DashboardController::class, 'contracts']);
        Route::get('/documents', [DashboardController::class, 'documents']);
        Route::get('/process-maps', [DashboardController::class, 'processMaps']);
    });
});

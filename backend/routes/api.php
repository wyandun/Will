<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BbAssignmentController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\FranchiseController;
use App\Http\Controllers\Api\FranchiseMemberController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SystemAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['status' => 'ok']));

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
        'data' => null,
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
// Invitations — public (no auth required)
// Verify token validity and accept an invitation (set password + auto-login).
// Note: Rate limiting (throttle:invitation) is applied here (Round 1, Finding R4)
// to prevent token enumeration/brute-force attacks.
// ---------------------------------------------------------------------------
Route::prefix('invitations')->middleware('throttle:invitation')->group(function () {
    Route::get('/{token}/verify', [InvitationController::class, 'verify']);
    Route::post('/{token}/accept', [InvitationController::class, 'accept']);
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
    // Franchises — member sub-routes must be declared before apiResource
    // so {franchise} doesn't capture literal strings like "members"/"admins"/"clients".
    Route::get('franchises/{franchise}/members', [FranchiseMemberController::class, 'members']);
    Route::post('franchises/{franchise}/admins', [FranchiseMemberController::class, 'storeAdmin']);
    Route::post('franchises/{franchise}/clients', [FranchiseMemberController::class, 'storeClient']);
    Route::patch('franchises/{franchise}/toggle-status', [FranchiseController::class, 'toggleStatus']);
    Route::apiResource('franchises', FranchiseController::class);

    // close-deal must be declared BEFORE apiResource to prevent {company}
    // from capturing the literal string "close-deal".
    Route::post('companies/close-deal', [CompanyController::class, 'closeDeal']);
    Route::apiResource('companies', CompanyController::class);

    Route::post('bb-assignments', [BbAssignmentController::class, 'store']);
    Route::delete('bb-assignments/{bbAssignment}', [BbAssignmentController::class, 'destroy']);

    Route::apiResource('system-admins', SystemAdminController::class)->only(['index', 'store', 'update', 'destroy']);

    // Invitations — protected management endpoints
    Route::get('invitations', [InvitationController::class, 'index']);
    Route::post('invitations', [InvitationController::class, 'store']);
    Route::post('invitations/{user}/resend', [InvitationController::class, 'resend']);
    Route::delete('invitations/{user}', [InvitationController::class, 'destroy']);

    // User profile
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::patch('/', [ProfileController::class, 'update']);
        Route::patch('/password', [ProfileController::class, 'updatePassword']);
        Route::post('/avatar', [ProfileController::class, 'uploadAvatar']);
    });

    // Feed
    Route::prefix('feed')->group(function () {
        Route::get('/posts', [FeedController::class, 'posts']);
        Route::get('/presence', [FeedController::class, 'presence']);
        Route::post('/posts', [FeedController::class, 'store']);
        Route::put('/posts/{id}', [FeedController::class, 'update']);
        Route::delete('/posts/{id}', [FeedController::class, 'destroy']);
    });

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

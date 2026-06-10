<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BbAssignmentController;
use App\Http\Controllers\Api\CatalogItemController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\FranchiseAdminController;
use App\Http\Controllers\Api\FranchiseClientController;
use App\Http\Controllers\Api\FranchiseController;
use App\Http\Controllers\Api\FranchiseMemberController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\ProcessCategoryController;
use App\Http\Controllers\Api\ProcessController;
use App\Http\Controllers\Api\ProcessMapController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SubProcessController;
use App\Http\Controllers\Api\SubSubProcessController;
use App\Http\Controllers\Api\SystemAdminController;
use App\Http\Controllers\Api\UserSearchController;
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
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Franchises
    // Sub-routes declared BEFORE apiResource to prevent the {franchise} wildcard
    // from capturing literal path segments like "members", "admins", "clients".
    // Franchise admin management (superadmin only)
    Route::prefix('franchises/{franchise}/admins/{user}')->group(function () {
        Route::patch('/', [FranchiseAdminController::class, 'update']);
        Route::patch('/password', [FranchiseAdminController::class, 'resetPassword']);
        Route::delete('/', [FranchiseAdminController::class, 'destroy']);
        Route::patch('/restore', [FranchiseAdminController::class, 'restore']);
        Route::get('/permissions', [FranchiseAdminController::class, 'permissions']);
        Route::put('/permissions', [FranchiseAdminController::class, 'updatePermissions']);
    });

    // Franchise client management (superadmin + admin_sm)
    Route::prefix('franchises/{franchise}/clients/{user}')->group(function () {
        Route::patch('/', [FranchiseClientController::class, 'update']);
        Route::patch('/password', [FranchiseClientController::class, 'resetPassword']);
        Route::delete('/', [FranchiseClientController::class, 'destroy']);
        Route::patch('/restore', [FranchiseClientController::class, 'restore']);
        Route::get('/permissions', [FranchiseClientController::class, 'permissions']);
        Route::put('/permissions', [FranchiseClientController::class, 'updatePermissions']);
    });

    Route::patch('franchises/{franchise}/toggle-status', [FranchiseController::class, 'toggleStatus']);
    Route::get('franchises/{franchise}/members', [FranchiseMemberController::class, 'members']);
    Route::apiResource('franchises', FranchiseController::class);

    // close-deal must be declared BEFORE apiResource to prevent {company}
    // from capturing the literal string "close-deal".
    Route::post('companies/close-deal', [CompanyController::class, 'closeDeal']);
    Route::apiResource('companies', CompanyController::class);

    Route::post('bb-assignments', [BbAssignmentController::class, 'store']);
    Route::delete('bb-assignments/{bbAssignment}', [BbAssignmentController::class, 'destroy']);

    Route::apiResource('system-admins', SystemAdminController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::apiResource('events', EventController::class)->middleware('module.permission:calendar');

    // Catalog (superadmin only). The /tree route MUST be registered BEFORE
    // apiResource so the {catalogItem} wildcard does not capture "tree".
    Route::get('catalog-items/tree', [CatalogItemController::class, 'tree']);
    Route::apiResource('catalog-items', CatalogItemController::class);

    Route::apiResource('process-maps', ProcessMapController::class)->only(['index', 'store', 'destroy']);
    Route::get('process-maps/{processMap}', [ProcessMapController::class, 'show']);
    Route::patch('process-categories/{processCategory}', [ProcessCategoryController::class, 'update']);
    Route::post('process-categories/{processCategory}/processes', [ProcessController::class, 'store']);
    Route::put('processes/{process}', [ProcessController::class, 'update']);
    Route::delete('processes/{process}', [ProcessController::class, 'destroy']);
    Route::post('processes/{process}/sub-processes', [SubProcessController::class, 'store']);
    Route::put('sub-processes/{subProcess}', [SubProcessController::class, 'update']);
    Route::delete('sub-processes/{subProcess}', [SubProcessController::class, 'destroy']);
    Route::post('sub-processes/{subProcess}/sub-sub-processes', [SubSubProcessController::class, 'store']);
    Route::put('sub-sub-processes/{subSubProcess}', [SubSubProcessController::class, 'update']);
    Route::delete('sub-sub-processes/{subSubProcess}', [SubSubProcessController::class, 'destroy']);

    // Lightweight user search for "Add Guests" in calendar events.
    Route::get('users/search', UserSearchController::class);

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
    Route::prefix('feed')->middleware('module.permission:feed')->group(function () {
        Route::get('/posts', [FeedController::class, 'posts']);
        Route::get('/presence', [FeedController::class, 'presence']);
        Route::post('/posts', [FeedController::class, 'store']);
        Route::put('/posts/{id}', [FeedController::class, 'update']);
        Route::delete('/posts/{id}', [FeedController::class, 'destroy']);
        Route::post('/posts/{postId}/react', [FeedController::class, 'react'])->middleware('throttle:60,1');
        Route::get('/posts/{postId}/comments', [FeedController::class, 'comments']);
        Route::post('/posts/{postId}/comments', [FeedController::class, 'addComment'])->middleware('throttle:30,1');
        Route::delete('/comments/{commentId}', [FeedController::class, 'deleteComment']);
    });

    // News (AI-curated from RSS — superadmin/admin_sm only)
    Route::prefix('news')->middleware('module.permission:feed')->group(function () {
        Route::get('/articles', [NewsController::class, 'index']);
        Route::post('/fetch', [NewsController::class, 'fetch']);
        Route::post('/articles/{newsArticle}/publish', [NewsController::class, 'publish']);
        Route::post('/articles/{newsArticle}/reject', [NewsController::class, 'reject']);
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    /**
     * Return the 4 dashboard KPI counters for the authenticated user.
     *
     * GET /api/v1/dashboard/kpis
     *
     * Each counter is scoped to the data the user is allowed to see
     * based on their role (superadmin, admin_sm, sb_owner, etc.).
     */
    public function kpis(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getKpis($request->user());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}

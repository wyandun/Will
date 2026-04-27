<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'kpis'         => $this->dashboardService->getKpis($user),
                'feed'         => $this->dashboardService->getFeed($user),
                'events'       => $this->dashboardService->getEvents($user),
                'tracking'     => $this->dashboardService->getTracking($user),
                'contracts'    => $this->dashboardService->getContracts($user),
                'documents'    => $this->dashboardService->getDocuments($user),
                'process_maps' => $this->dashboardService->getProcessMaps($user),
            ],
        ]);
    }

    public function kpis(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getKpis($request->user());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getFeed($request->user());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getEvents($request->user());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function tracking(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getTracking($request->user());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function contracts(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getContracts($request->user());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getDocuments($request->user());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function processMaps(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getProcessMaps($request->user());

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Franchise\StoreFranchiseRequest;
use App\Http\Requests\Franchise\UpdateFranchiseRequest;
use App\Http\Resources\FranchiseResource;
use App\Models\Franchise;
use App\Services\FranchiseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FranchiseController extends Controller
{
    public function __construct(private FranchiseService $franchiseService) {}

    /**
     * List franchises visible to the authenticated user.
     *
     * GET /api/v1/franchises
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Franchise::class);

        $franchises = $this->franchiseService->list($request->user());

        return FranchiseResource::collection($franchises);
    }

    /**
     * Create a new franchise.
     *
     * POST /api/v1/franchises
     */
    public function store(StoreFranchiseRequest $request): JsonResponse
    {
        $this->authorize('create', Franchise::class);

        $franchise = $this->franchiseService->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new FranchiseResource($franchise),
            'message' => 'Franquicia creada correctamente.',
        ], 201);
    }

    /**
     * Return a single franchise.
     *
     * GET /api/v1/franchises/{franchise}
     */
    public function show(Franchise $franchise): JsonResponse
    {
        $this->authorize('view', $franchise);

        return response()->json([
            'success' => true,
            'data' => new FranchiseResource($franchise),
            'message' => 'OK.',
        ]);
    }

    /**
     * Update an existing franchise (PATCH semantics — all fields optional).
     *
     * PUT/PATCH /api/v1/franchises/{franchise}
     */
    public function update(UpdateFranchiseRequest $request, Franchise $franchise): JsonResponse
    {
        $this->authorize('update', $franchise);

        $franchise = $this->franchiseService->update($franchise, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new FranchiseResource($franchise),
            'message' => 'Franquicia actualizada correctamente.',
        ]);
    }

    /**
     * Toggle franchise active/inactive status.
     *
     * PATCH /api/v1/franchises/{franchise}/toggle-status
     */
    public function toggleStatus(Franchise $franchise): JsonResponse
    {
        $this->authorize('toggleStatus', $franchise);

        $franchise = $this->franchiseService->toggleStatus($franchise);

        return response()->json([
            'success' => true,
            'data' => new FranchiseResource($franchise),
            'message' => $franchise->is_active
                ? 'Franquicia activada correctamente.'
                : 'Franquicia desactivada correctamente.',
        ]);
    }

    /**
     * Delete a franchise.
     *
     * DELETE /api/v1/franchises/{franchise}
     */
    public function destroy(Franchise $franchise): JsonResponse
    {
        $this->authorize('delete', $franchise);

        $this->franchiseService->delete($franchise);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Franquicia eliminada correctamente.',
        ]);
    }
}

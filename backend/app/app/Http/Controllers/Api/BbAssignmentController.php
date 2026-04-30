<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BbAssignment\StoreBbAssignmentRequest;
use App\Models\BbAssignment;
use App\Services\BbAssignmentService;
use Illuminate\Http\JsonResponse;

class BbAssignmentController extends Controller
{
    public function __construct(private BbAssignmentService $bbAssignmentService) {}

    /**
     * Assign a BB user to a company.
     *
     * POST /api/v1/bb-assignments
     * Only superadmin and admin_sm can perform this action.
     */
    public function store(StoreBbAssignmentRequest $request): JsonResponse
    {
        $this->authorize('create', BbAssignment::class);

        $user = $request->user();

        $assignment = $this->bbAssignmentService->assign($request->validated(), $user);

        return response()->json([
            'success' => true,
            'data' => $assignment,
            'message' => 'Business Bishop asignado correctamente.',
        ], 201);
    }

    /**
     * Remove a BB assignment.
     *
     * DELETE /api/v1/bb-assignments/{bbAssignment}
     * Only superadmin and admin_sm can perform this action.
     */
    public function destroy(BbAssignment $bbAssignment): JsonResponse
    {
        $this->authorize('delete', $bbAssignment);

        $this->bbAssignmentService->unassign($bbAssignment);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Business Bishop desasignado correctamente.',
        ]);
    }
}

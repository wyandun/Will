<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Franchise\StoreFranchiseAdminRequest;
use App\Http\Requests\Franchise\StoreFranchiseClientRequest;
use App\Http\Resources\FranchiseMemberResource;
use App\Models\Franchise;
use App\Services\FranchiseMemberService;
use Illuminate\Http\JsonResponse;

class FranchiseMemberController extends Controller
{
    public function __construct(private FranchiseMemberService $service) {}

    /**
     * List all admin_sm users and client users (sb_owner, bb_employee) for a franchise.
     *
     * GET /api/v1/franchises/{franchise}/members
     */
    public function members(Franchise $franchise): JsonResponse
    {
        $this->authorize('view', $franchise);

        return response()->json([
            'success' => true,
            'data' => $this->service->getMembers($franchise),
        ]);
    }

    /**
     * Create a new admin_sm user and associate them with the franchise.
     *
     * POST /api/v1/franchises/{franchise}/admins
     */
    public function storeAdmin(StoreFranchiseAdminRequest $request, Franchise $franchise): JsonResponse
    {
        $this->authorize('addMember', $franchise);

        $user = $this->service->createAdmin($franchise, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new FranchiseMemberResource($user),
            'message' => 'franchise_detail.admin_created',
        ], 201);
    }

    /**
     * Create a new client user (sb_owner or bb_employee) and associate them with the franchise.
     *
     * POST /api/v1/franchises/{franchise}/clients
     */
    public function storeClient(StoreFranchiseClientRequest $request, Franchise $franchise): JsonResponse
    {
        $this->authorize('addMember', $franchise);

        $user = $this->service->createClient($franchise, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new FranchiseMemberResource($user),
            'message' => 'franchise_detail.client_created',
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Services\FranchiseMemberService;
use Illuminate\Http\JsonResponse;

class FranchiseMemberController extends Controller
{
    public function __construct(private readonly FranchiseMemberService $service) {}

    /**
     * List all admins and clients for the given franchise.
     */
    public function members(Franchise $franchise): JsonResponse
    {
        $this->authorize('view', $franchise);

        return response()->json([
            'success' => true,
            'data' => $this->service->listMembers($franchise),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
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

    /**
     * List sub-franchises belonging to this SM franchise (via companies).
     * Used by the AddClientModal to populate the sub-franchise selector.
     */
    public function subFranchises(Franchise $franchise): JsonResponse
    {
        $this->authorize('view', $franchise);

        $subFranchises = Franchise::whereIn(
            'parent_company_id',
            Company::where('sm_franchise_id', $franchise->id)->select('id')
        )
            ->where('type', 'sub')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_company_id', 'is_active']);

        return response()->json([
            'success' => true,
            'data' => $subFranchises,
        ]);
    }
}

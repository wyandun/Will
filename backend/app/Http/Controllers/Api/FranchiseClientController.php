<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\FranchiseClient\DestroyFranchiseClientRequest;
use App\Http\Requests\FranchiseClient\RestoreFranchiseClientRequest;
use App\Http\Requests\FranchiseClient\UpdateFranchiseClientPasswordRequest;
use App\Http\Requests\FranchiseClient\UpdateFranchiseClientPermissionsRequest;
use App\Http\Requests\FranchiseClient\UpdateFranchiseClientRequest;
use App\Http\Resources\FranchiseClientResource;
use App\Models\Franchise;
use App\Models\User;
use App\Services\FranchiseClientService;
use Illuminate\Http\JsonResponse;

class FranchiseClientController extends Controller
{
    public function __construct(private FranchiseClientService $franchiseClientService) {}

    /**
     * Update a franchise client's profile fields.
     *
     * Note: The authorize() + ensureClientBelongsToFranchise() two-step pattern mirrors
     * FranchiseAdminController. The policy checks role-level access (superadmin or admin_sm),
     * while the helper verifies franchise scoping and client role with distinct HTTP status codes.
     */
    public function update(UpdateFranchiseClientRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseClient', $user);
        $this->ensureClientBelongsToFranchise($user, $franchise);

        $user = $this->franchiseClientService->update($user, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new FranchiseClientResource($user),
            'message' => 'franchise_client.updated_success',
        ]);
    }

    /**
     * Reset a franchise client's password and revoke existing tokens.
     *
     * Note: Intentional duplication with FranchiseAdminController::resetPassword().
     * See that controller for the rationale (5+ abstract hooks required, no net
     * LOC savings, would obscure the cascade behavior in destroy()).
     */
    public function resetPassword(UpdateFranchiseClientPasswordRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseClient', $user);
        $this->ensureClientBelongsToFranchise($user, $franchise);

        $this->franchiseClientService->resetPassword($user, $request->validated('password'));

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'franchise_client.password_reset_success',
        ]);
    }

    /**
     * Deactivate a franchise client (soft delete) and revoke tokens.
     *
     * If the client is an SB Owner, all investors (bb_employee) linked to the
     * same company are also deactivated within the same transaction. This
     * cascade lives entirely in the service.
     */
    public function destroy(DestroyFranchiseClientRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('deleteFranchiseClient', $user);
        $this->ensureClientBelongsToFranchise($user, $franchise);

        $this->franchiseClientService->deactivate($user);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'franchise_client.deactivated_success',
        ]);
    }

    /**
     * Restore a soft-deleted franchise client.
     *
     * Note on $userId (int) vs User model binding: Laravel's route model binding excludes
     * soft-deleted records by default (SoftDeletes global scope). Since this method targets
     * a soft-deleted user, the service queries with withTrashed() manually.
     */
    public function restore(RestoreFranchiseClientRequest $request, Franchise $franchise, int $userId): JsonResponse
    {
        $this->authorize('restoreFranchiseClient', User::class);

        $user = $this->franchiseClientService->restore($franchise, $userId);

        return response()->json([
            'success' => true,
            'data' => new FranchiseClientResource($user),
            'message' => 'franchise_client.restored_success',
        ]);
    }

    /**
     * Get current module permissions for a franchise client.
     */
    public function permissions(Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('viewFranchiseClientPermissions', $user);
        $this->ensureClientBelongsToFranchise($user, $franchise);

        return response()->json([
            'success' => true,
            'data' => $this->franchiseClientService->getPermissions($user),
        ]);
    }

    /**
     * Batch-update module permissions for a franchise client.
     *
     * Note: Module names are validated against UserPermission::ALL_MODULES allowlist
     * in the FormRequest layer (Rule::in). Arbitrary module injection is blocked
     * before this method executes.
     */
    public function updatePermissions(UpdateFranchiseClientPermissionsRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseClientPermissions', $user);
        $this->ensureClientBelongsToFranchise($user, $franchise);

        $this->franchiseClientService->updatePermissions($user, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new FranchiseClientResource($user->fresh()->load('userPermissions')),
            'message' => 'franchise_client.permissions_updated_success',
        ]);
    }

    /**
     * Verify the user belongs to the given franchise and has a client role.
     */
    private function ensureClientBelongsToFranchise(User $user, Franchise $franchise): void
    {
        abort_unless(
            (int) $user->sm_franchise_id === (int) $franchise->id,
            404,
            'franchise_client.not_found'
        );

        abort_unless($user->hasAnyRole([Role::SB_OWNER, Role::BB_EMPLOYEE]), 403);
    }
}

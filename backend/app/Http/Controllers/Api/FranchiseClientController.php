<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\FranchiseClient\UpdateFranchiseClientPasswordRequest;
use App\Http\Requests\FranchiseClient\UpdateFranchiseClientPermissionsRequest;
use App\Http\Requests\FranchiseClient\UpdateFranchiseClientRequest;
use App\Http\Resources\FranchiseAdminResource;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class FranchiseClientController extends Controller
{
    /**
     * Update a franchise client's profile fields.
     *
     * Clients are sb_owner or bb_employee users belonging to the franchise.
     * The authorize() + ensureBelongsToFranchise() two-step pattern is intentional —
     * same reasoning as FranchiseAdminController: policy checks role-level access,
     * ensureBelongsToFranchise() checks franchise scoping with distinct HTTP codes.
     */
    public function update(UpdateFranchiseClientRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseClient', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new FranchiseAdminResource($user->fresh()->load('userPermissions')),
            'message' => 'franchise_client.updated_success',
        ]);
    }

    /**
     * Reset a franchise client's password and revoke existing tokens.
     */
    public function resetPassword(UpdateFranchiseClientPasswordRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseClient', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        $user->password = Hash::make($request->validated('password'));
        $user->save();

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'franchise_client.password_reset_success',
        ]);
    }

    /**
     * Deactivate a franchise client (soft delete) and revoke tokens.
     */
    public function destroy(Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('deleteFranchiseClient', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'franchise_client.deactivated_success',
        ]);
    }

    /**
     * Restore a soft-deleted franchise client.
     *
     * Receives raw $userId (int) instead of route model binding because
     * SoftDeletes excludes soft-deleted records from the default scope.
     */
    public function restore(Franchise $franchise, int $userId): JsonResponse
    {
        $this->authorize('restoreFranchiseClient', User::class);

        $user = User::withTrashed()
            ->where('id', $userId)
            ->where('sm_franchise_id', $franchise->id)
            ->firstOrFail();

        abort_unless($user->hasAnyRole([Role::SB_OWNER, Role::BB_EMPLOYEE, Role::SUB_FRANCHISE_OWNER, Role::SUB_FRANCHISE_ADMIN]), 403);
        abort_unless($user->trashed(), 422, 'franchise_client.not_deactivated');

        $user->restore();

        return response()->json([
            'success' => true,
            'data' => new FranchiseAdminResource($user->load('userPermissions')),
            'message' => 'franchise_client.restored_success',
        ]);
    }

    /**
     * Get current module permissions for a franchise client.
     */
    public function permissions(Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseClientPermissions', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        return response()->json([
            'success' => true,
            'data' => $user->userPermissions->map(fn ($p) => [
                'module' => $p->module,
                'can_read' => $p->can_read,
                'can_write' => $p->can_write,
            ]),
        ]);
    }

    /**
     * Batch-update module permissions for a franchise client.
     */
    public function updatePermissions(UpdateFranchiseClientPermissionsRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseClientPermissions', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        UserPermission::updateForUser($user->id, $request->validated('permissions'));

        return response()->json([
            'success' => true,
            'data' => new FranchiseAdminResource($user->fresh()->load('userPermissions')),
            'message' => 'franchise_client.permissions_updated_success',
        ]);
    }

    /**
     * Verify the user belongs to the given franchise and has a client role (sb_owner or bb_employee).
     *
     * Returns 404 for franchise mismatch (avoids confirming cross-franchise user existence),
     * 403 for wrong role. Policy layer (superadmin/admin_sm) gates access before this runs.
     */
    private function ensureBelongsToFranchise(User $user, Franchise $franchise): void
    {
        abort_unless(
            (int) $user->sm_franchise_id === (int) $franchise->id,
            404,
            'franchise_client.not_found'
        );

        abort_unless($user->hasAnyRole([Role::SB_OWNER, Role::BB_EMPLOYEE, Role::SUB_FRANCHISE_OWNER, Role::SUB_FRANCHISE_ADMIN]), 403);
    }
}

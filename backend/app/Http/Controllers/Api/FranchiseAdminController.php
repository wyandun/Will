<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\FranchiseAdmin\UpdateFranchiseAdminPasswordRequest;
use App\Http\Requests\FranchiseAdmin\UpdateFranchiseAdminPermissionsRequest;
use App\Http\Requests\FranchiseAdmin\UpdateFranchiseAdminRequest;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class FranchiseAdminController extends Controller
{
    /**
     * Update a franchise admin's profile fields.
     */
    public function update(UpdateFranchiseAdminRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseAdmin', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        $validated = $request->validated();

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data' => $user->fresh()->load('userPermissions'),
            'message' => 'franchise_admin.updated_success',
        ]);
    }

    /**
     * Reset a franchise admin's password and revoke existing tokens.
     */
    public function resetPassword(UpdateFranchiseAdminPasswordRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseAdmin', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        $user->password = Hash::make($request->validated('password'));
        $user->save();

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'franchise_admin.password_reset_success',
        ]);
    }

    /**
     * Deactivate a franchise admin (soft delete) and revoke tokens.
     */
    public function destroy(Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('deleteFranchiseAdmin', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'franchise_admin.deactivated_success',
        ]);
    }

    /**
     * Restore a soft-deleted franchise admin.
     */
    public function restore(Franchise $franchise, int $userId): JsonResponse
    {
        $this->authorize('restoreFranchiseAdmin', User::class);

        $user = User::withTrashed()
            ->where('id', $userId)
            ->where('sm_franchise_id', $franchise->id)
            ->firstOrFail();

        abort_unless($user->hasRole(Role::ADMIN_SM), 403);
        abort_unless($user->trashed(), 422, 'franchise_admin.not_deactivated');

        $user->restore();

        return response()->json([
            'success' => true,
            'data' => $user->load('userPermissions'),
            'message' => 'franchise_admin.restored_success',
        ]);
    }

    /**
     * Get current module permissions for a franchise admin.
     */
    public function permissions(Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseAdminPermissions', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        return response()->json([
            'success' => true,
            'data' => $user->userPermissions,
        ]);
    }

    /**
     * Batch-update module permissions for a franchise admin.
     */
    public function updatePermissions(UpdateFranchiseAdminPermissionsRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseAdminPermissions', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        UserPermission::updateForUser($user->id, $request->validated('permissions'));

        return response()->json([
            'success' => true,
            'data' => $user->fresh()->load('userPermissions'),
            'message' => 'franchise_admin.permissions_updated_success',
        ]);
    }

    /**
     * Verify the user belongs to the given franchise and has the admin_sm role.
     */
    private function ensureBelongsToFranchise(User $user, Franchise $franchise): void
    {
        abort_unless(
            (int) $user->sm_franchise_id === (int) $franchise->id,
            404,
            'franchise_admin.not_found'
        );

        abort_unless($user->hasRole(Role::ADMIN_SM), 403);
    }
}

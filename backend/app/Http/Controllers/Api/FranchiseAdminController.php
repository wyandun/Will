<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\FranchiseAdmin\UpdateFranchiseAdminPasswordRequest;
use App\Http\Requests\FranchiseAdmin\UpdateFranchiseAdminPermissionsRequest;
use App\Http\Requests\FranchiseAdmin\UpdateFranchiseAdminRequest;
use App\Http\Resources\FranchiseAdminResource;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class FranchiseAdminController extends Controller
{
    /**
     * Update a franchise admin's profile fields.
     *
     * Note: The authorize() + ensureBelongsToFranchise() two-step pattern is intentional.
     * The policy checks role-level access (superadmin only), while ensureBelongsToFranchise()
     * verifies franchise scoping and admin_sm role with distinct HTTP status codes (404/403).
     * Merging both into the policy would lose the 404 distinction and require passing
     * the Franchise model into every policy method. This pattern repeats across all
     * franchise-scoped endpoints for consistency.
     */
    public function update(UpdateFranchiseAdminRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseAdmin', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        $validated = $request->validated();

        $user->update($validated);

        return response()->json([
            'success' => true,
            // fresh() re-fetches from DB to ensure response reflects any DB-level
            // defaults or mutators, guaranteeing consistency with persisted state.
            'data' => new FranchiseAdminResource($user->fresh()->load('userPermissions')),
            'message' => 'franchise_admin.updated_success',
        ]);
    }

    /**
     * Reset a franchise admin's password and revoke existing tokens.
     *
     * Note: The save-then-revoke order is intentional. If save() fails, an exception
     * is thrown and tokens remain valid (user keeps access with old password). If
     * tokens()->delete() fails after save, the password is updated but old sessions
     * remain — the admin can retry, and sessions expire naturally. Wrapping both in
     * a DB transaction is unnecessary and potentially harmful (Sanctum tokens may
     * use a separate connection).
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
     *
     * Note: The method is named destroy() following Laravel's resource controller convention
     * for DELETE routes. "Deactivate" is the domain term (user-facing), while "destroy" is
     * the framework convention. The soft delete behavior is an implementation detail.
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
     *
     * Note on $userId (int) vs User model binding: Laravel's route model binding excludes
     * soft-deleted records by default (SoftDeletes global scope). Since this method targets
     * a soft-deleted user, we receive the raw ID and query with withTrashed() manually.
     *
     * Note on User::class: The policy method restoreFranchiseAdmin(User $user) takes only
     * the authenticated user (no target model) because the target doesn't exist in normal
     * queries. Passing User::class is the standard Laravel convention for policies that
     * don't require a model instance (same pattern as 'inviteUsers' policy).
     */
    public function restore(Franchise $franchise, int $userId): JsonResponse
    {
        $this->authorize('restoreFranchiseAdmin', User::class);

        $user = User::withTrashed()
            ->where('id', $userId)
            ->where('sm_franchise_id', $franchise->id)
            ->firstOrFail();

        abort_unless($user->hasRole(Role::ADMIN_SM), 403);
        // Translation keys (e.g. 'franchise_admin.not_deactivated') are passed as raw
        // strings to abort(). The frontend translates them via i18next. This is the
        // project-wide convention — see also SystemAdminController and InvitationController.
        abort_unless($user->trashed(), 422, 'franchise_admin.not_deactivated');

        $user->restore();

        return response()->json([
            'success' => true,
            'data' => new FranchiseAdminResource($user->load('userPermissions')),
            'message' => 'franchise_admin.restored_success',
        ]);
    }

    /**
     * Get current module permissions for a franchise admin.
     *
     * Note: userPermissions is accessed via lazy load on a single model instance
     * (not inside a collection loop), so this is a simple 1+1 query — not an N+1.
     * Explicit eager loading (->load()) would be semantically identical here.
     */
    public function permissions(Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseAdminPermissions', $user);
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
     * Batch-update module permissions for a franchise admin.
     */
    public function updatePermissions(UpdateFranchiseAdminPermissionsRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseAdminPermissions', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        UserPermission::updateForUser($user->id, $request->validated('permissions'));

        return response()->json([
            'success' => true,
            'data' => new FranchiseAdminResource($user->fresh()->load('userPermissions')),
            'message' => 'franchise_admin.permissions_updated_success',
        ]);
    }

    /**
     * Verify the user belongs to the given franchise and has the admin_sm role.
     *
     * Note on check order (404 before 403): This is intentional. The franchise membership
     * check returns 404 to avoid confirming user existence across franchises, while the
     * role check returns 403. Only superadmin reaches this code (policy layer blocks
     * everyone else first), so the 404/403 distinction provides useful debugging info
     * without any security risk — superadmin already has full visibility into franchise
     * membership via other endpoints.
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

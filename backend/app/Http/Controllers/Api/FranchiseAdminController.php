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
use Illuminate\Support\Facades\DB;
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
     * Both operations target the same PostgreSQL connection (sm_portal), so
     * a transaction ensures atomicity: if token revocation fails, the password
     * change rolls back and the user retains their old credentials + sessions.
     */
    public function resetPassword(UpdateFranchiseAdminPasswordRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseAdmin', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        DB::transaction(function () use ($user, $request) {
            $user->password = Hash::make($request->validated('password'));
            $user->save();

            $user->tokens()->delete();
        });

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'franchise_admin.password_reset_success',
        ]);
    }

    /**
     * Deactivate a franchise admin (soft delete) and revoke tokens.
     *
     * Both operations target the same PostgreSQL connection (sm_portal), so
     * a transaction ensures atomicity: if the soft delete fails, token
     * revocation rolls back and the user retains their active sessions.
     *
     * Note: The method is named destroy() following Laravel's resource controller convention
     * for DELETE routes. "Deactivate" is the domain term (user-facing), while "destroy" is
     * the framework convention. The soft delete behavior is an implementation detail.
     */
    public function destroy(Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('deleteFranchiseAdmin', $user);
        $this->ensureBelongsToFranchise($user, $franchise);

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

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
     * Note on ensureBelongsToFranchise(): This method cannot use the shared helper because
     * it requires withTrashed() querying. The franchise scope check is performed inline via
     * ->where('sm_franchise_id', $franchise->id)->firstOrFail() (equivalent 404 behavior),
     * and the role check via abort_unless($user->hasRole(...), 403).
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

        // Spatie roles live in the model_has_roles pivot table, unaffected by soft deletes.
        // hasRole() works correctly on trashed models.
        abort_unless($user->hasRole(Role::ADMIN_SM), 403);
        // A concurrent restore() is a no-op at the DB level (UPDATE SET deleted_at=NULL
        // on an already-NULL row). The 422 guard is a UX convenience, not a safety lock.
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
     * Explicit eager loading (->load()) would be semantically identical here since
     * $user comes from route model binding (fresh instance, no pre-loaded relations).
     *
     * The response format intentionally differs from updatePermissions(): this endpoint
     * returns a flat permissions array (lightweight read), while updatePermissions()
     * returns a full FranchiseAdminResource (the frontend refreshes the admin card
     * after mutation). Both serialize the same {module, can_read, can_write} shape.
     */
    public function permissions(Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('viewFranchiseAdminPermissions', $user);
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
     *
     * Note: Module names are validated against UserPermission::ALL_MODULES allowlist
     * in the FormRequest layer (Rule::in). Arbitrary module injection is blocked
     * before this method executes. The model's updateForUser() trusts validated input.
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

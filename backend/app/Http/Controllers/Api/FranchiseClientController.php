<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\FranchiseClient\UpdateFranchiseClientPasswordRequest;
use App\Http\Requests\FranchiseClient\UpdateFranchiseClientPermissionsRequest;
use App\Http\Requests\FranchiseClient\UpdateFranchiseClientRequest;
use App\Http\Resources\FranchiseClientResource;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class FranchiseClientController extends Controller
{
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

        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new FranchiseClientResource($user->fresh()->load('userPermissions')),
            'message' => 'franchise_client.updated_success',
        ]);
    }

    /**
     * Reset a franchise client's password and revoke existing tokens.
     *
     * Both operations target the same PostgreSQL connection (sm_portal), so
     * a transaction ensures atomicity: if token revocation fails, the password
     * change rolls back and the user retains their old credentials + sessions.
     *
     * Note: Intentional duplication with FranchiseAdminController::resetPassword().
     * See that controller for the rationale (5+ abstract hooks required, no net
     * LOC savings, would obscure the cascade behavior in destroy()).
     */
    public function resetPassword(UpdateFranchiseClientPasswordRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseClient', $user);
        $this->ensureClientBelongsToFranchise($user, $franchise);

        DB::transaction(function () use ($user, $request) {
            $user->password = Hash::make($request->validated('password'));
            $user->save();

            $user->tokens()->delete();
        });

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'franchise_client.password_reset_success',
        ]);
    }

    /**
     * Deactivate a franchise client (soft delete) and revoke tokens.
     *
     * Both operations target the same PostgreSQL connection (sm_portal), so
     * a transaction ensures atomicity. If the client is an SB Owner, all
     * investors (bb_employee) linked to the same company are also deactivated
     * within the same transaction to maintain data consistency.
     */
    public function destroy(Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('deleteFranchiseClient', $user);
        $this->ensureClientBelongsToFranchise($user, $franchise);

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();

            // Cascade: deactivate all investors linked to the same company.
            // Bulk-query the IDs once, then revoke tokens and soft-delete in two
            // queries each instead of 2N per-model calls. Safe here because no
            // UserObserver exists and `tokenable_type` is stored as the full FQCN
            // (no morph map). If either changes, revert to a per-model loop.
            if ($user->hasRole(Role::SB_OWNER) && $user->company_id) {
                $investorIds = User::where('company_id', $user->company_id)
                    ->role(Role::BB_EMPLOYEE)
                    ->pluck('id');

                if ($investorIds->isNotEmpty()) {
                    PersonalAccessToken::where('tokenable_type', User::class)
                        ->whereIn('tokenable_id', $investorIds)
                        ->delete();

                    User::whereIn('id', $investorIds)->delete();
                }
            }
        });

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
     * a soft-deleted user, we receive the raw ID and query with withTrashed() manually.
     *
     * Note on ensureClientBelongsToFranchise(): This method cannot use the shared helper
     * because it requires withTrashed() querying. The franchise scope check is performed
     * inline via ->where('sm_franchise_id', $franchise->id)->firstOrFail() (equivalent
     * 404 behavior), and the role check via abort_unless().
     */
    public function restore(Franchise $franchise, int $userId): JsonResponse
    {
        $this->authorize('restoreFranchiseClient', User::class);

        $user = User::withTrashed()
            ->where('id', $userId)
            ->where('sm_franchise_id', $franchise->id)
            ->firstOrFail();

        // Defense-in-depth: the policy already allows superadmin + admin_sm, but
        // this role allowlist ensures the target is actually a franchise client
        // (not an admin who was mistargeted via /clients/{id}/restore).
        abort_unless($user->hasAnyRole([Role::SB_OWNER, Role::BB_EMPLOYEE]), 403);

        // No lockForUpdate needed: restore() is a single idempotent UPDATE
        // (deleted_at=NULL) with no dependent writes; the trashed() guard is
        // UX-only, and destroy() already wraps its token-revoke + soft-delete in
        // its own transaction. All 4 concurrent destroy/restore interleavings
        // converge to a consistent state — see audit decision in commit history.
        abort_unless($user->trashed(), 422, 'franchise_client.not_deactivated');

        // Investors can be restored independently of their SB Owner — the admin
        // may be reassigning them to a different owner later. Restoring an owner
        // does NOT cascade-restore investors (deliberate asymmetry with the
        // deactivate cascade in destroy()).
        $user->restore();

        return response()->json([
            'success' => true,
            'data' => new FranchiseClientResource($user->load('userPermissions')),
            'message' => 'franchise_client.restored_success',
        ]);
    }

    /**
     * Get current module permissions for a franchise client.
     *
     * Note: userPermissions is accessed via lazy load on a single model instance
     * (not inside a collection loop), so this is a simple 1+1 query — not an N+1.
     */
    public function permissions(Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('viewFranchiseClientPermissions', $user);
        $this->ensureClientBelongsToFranchise($user, $franchise);

        // See FranchiseAdminController::permissions() for the rationale on keeping
        // this shape inline across 4 sites instead of extracting a Resource.
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
     *
     * Note: Module names are validated against UserPermission::ALL_MODULES allowlist
     * in the FormRequest layer (Rule::in). Arbitrary module injection is blocked
     * before this method executes.
     */
    public function updatePermissions(UpdateFranchiseClientPermissionsRequest $request, Franchise $franchise, User $user): JsonResponse
    {
        $this->authorize('updateFranchiseClientPermissions', $user);
        $this->ensureClientBelongsToFranchise($user, $franchise);

        UserPermission::updateForUser($user->id, $request->validated('permissions'));

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

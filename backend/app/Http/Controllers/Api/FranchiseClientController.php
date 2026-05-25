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

            // Cascade: deactivate all investors linked to the same company
            if ($user->hasRole(Role::SB_OWNER) && $user->company_id) {
                $investors = User::where('company_id', $user->company_id)
                    ->role(Role::BB_EMPLOYEE)
                    ->get();

                foreach ($investors as $investor) {
                    $investor->tokens()->delete();
                    $investor->delete();
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

        abort_unless($user->hasAnyRole([Role::SB_OWNER, Role::BB_EMPLOYEE]), 403);
        abort_unless($user->trashed(), 422, 'franchise_client.not_deactivated');

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

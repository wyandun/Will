<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class FranchiseClientService
{
    /**
     * Update a franchise client's profile fields.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $client, array $data): User
    {
        $client->update($data);

        return $client->fresh()->load('userPermissions');
    }

    /**
     * Reset a franchise client's password and revoke existing tokens.
     *
     * Both operations target the same connection so a transaction ensures
     * atomicity: if token revocation fails, the password change rolls back
     * and the user retains their old credentials + sessions.
     */
    public function resetPassword(User $client, string $newPassword): void
    {
        DB::transaction(function () use ($client, $newPassword) {
            $client->password = Hash::make($newPassword);
            $client->save();

            $client->tokens()->delete();
        });
    }

    /**
     * Deactivate a franchise client (soft delete) and revoke tokens.
     *
     * If the client is an SB Owner, all investors (bb_employee) linked to the
     * same company are also deactivated within the same transaction to keep
     * data consistent.
     */
    public function deactivate(User $client): void
    {
        DB::transaction(function () use ($client) {
            $client->tokens()->delete();
            $client->delete();

            // Cascade: deactivate all investors linked to the same company.
            // Bulk-query the IDs once, then revoke tokens and soft-delete in two
            // queries each instead of 2N per-model calls. Safe because no
            // UserObserver exists and `tokenable_type` is the full FQCN
            // (no morph map). If either changes, revert to a per-model loop.
            if ($client->hasRole(Role::SB_OWNER) && $client->company_id) {
                $investorIds = User::where('company_id', $client->company_id)
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
    }

    /**
     * Restore a soft-deleted franchise client.
     *
     * The franchise scope check + role allowlist + trashed guard live here
     * because route model binding excludes soft-deleted users.
     */
    public function restore(Franchise $franchise, int $userId): User
    {
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
        // UX-only, and deactivate() already wraps its token-revoke + soft-delete
        // in its own transaction.
        abort_unless($user->trashed(), 422, 'franchise_client.not_deactivated');

        // Investors can be restored independently of their SB Owner — the admin
        // may be reassigning them to a different owner later. Restoring an owner
        // does NOT cascade-restore investors (deliberate asymmetry with the
        // deactivate cascade in deactivate()).
        $user->restore();

        return $user->load('userPermissions');
    }

    /**
     * Return the franchise client's module permissions as a flat collection
     * of { module, can_read, can_write } rows.
     *
     * @return Collection<int, array{module: string, can_read: bool, can_write: bool}>
     */
    public function getPermissions(User $client): Collection
    {
        return $client->userPermissions->map(fn ($p) => [
            'module' => (string) $p->module,
            'can_read' => (bool) $p->can_read,
            'can_write' => (bool) $p->can_write,
        ]);
    }

    /**
     * Batch-update the franchise client's module permissions.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePermissions(User $client, array $data): void
    {
        UserPermission::updateForUser($client->id, $data['permissions']);
    }
}

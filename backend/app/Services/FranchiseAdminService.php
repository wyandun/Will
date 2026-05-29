<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FranchiseAdminService
{
    /**
     * Update a franchise admin's profile fields.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $admin, array $data): User
    {
        $admin->update($data);

        return $admin->fresh()->load('userPermissions');
    }

    /**
     * Reset a franchise admin's password and revoke existing tokens.
     *
     * Both operations target the same connection so a transaction ensures
     * atomicity: if token revocation fails, the password change rolls back
     * and the user retains their old credentials + sessions.
     */
    public function resetPassword(User $admin, string $newPassword): void
    {
        DB::transaction(function () use ($admin, $newPassword) {
            $admin->password = Hash::make($newPassword);
            $admin->save();

            $admin->tokens()->delete();
        });
    }

    /**
     * Deactivate a franchise admin (soft delete) and revoke tokens.
     *
     * Both writes (token revocation + soft delete) are wrapped in DB::transaction
     * to ensure atomicity: if either fails, neither is committed and the admin
     * retains their active sessions. No cascade is needed for admins (unlike
     * FranchiseClientService::deactivate which must also deactivate investors).
     */
    public function deactivate(User $admin): void
    {
        DB::transaction(function () use ($admin) {
            $admin->tokens()->delete();
            $admin->delete();
        });
    }

    /**
     * Restore a soft-deleted franchise admin.
     *
     * The franchise scope check + role allowlist + trashed guard live here (not in the
     * controller) because route model binding excludes soft-deleted users — the controller
     * receives a raw int and delegates immediately. Guards intentionally moved to the
     * service to keep the controller thin:
     *   - abort_unless(ADMIN_SM, 403): ensures the target is actually a franchise admin,
     *     not an unrelated user whose ID was guessed.
     *   - abort_unless(trashed(), 422): prevents a silent no-op 200 on already-active users
     *     (Eloquent restore() on a non-trashed model returns false without throwing).
     */
    public function restore(Franchise $franchise, int $userId): User
    {
        $user = User::withTrashed()
            ->where('id', $userId)
            ->where('sm_franchise_id', $franchise->id)
            ->firstOrFail();

        abort_unless($user->hasRole(Role::ADMIN_SM), 403);
        abort_unless($user->trashed(), 422, 'franchise_admin.not_deactivated');

        $user->restore();

        return $user->load('userPermissions');
    }

    /**
     * Return the franchise admin's module permissions as a flat collection
     * of { module, can_read, can_write } rows.
     *
     * This is a pure data mapper — no authorization logic here by design.
     * The 'viewFranchiseAdminPermissions' policy check and ensureBelongsToFranchise()
     * scope guard both run in the controller before this method is called.
     *
     * @return Collection<int, array{module: string, can_read: bool, can_write: bool}>
     */
    public function getPermissions(User $admin): Collection
    {
        return $admin->userPermissions->map(fn ($p) => [
            'module' => (string) $p->module,
            'can_read' => (bool) $p->can_read,
            'can_write' => (bool) $p->can_write,
        ]);
    }

    /**
     * Batch-update the franchise admin's module permissions.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePermissions(User $admin, array $data): void
    {
        UserPermission::updateForUser($admin->id, $data['permissions']);
    }
}

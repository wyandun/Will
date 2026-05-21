<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;

class FranchisePolicy
{
    /**
     * List franchises: superadmin/system_admin/system_admin_readonly see all,
     * admin_sm sees theirs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::SYSTEM_ADMIN_READONLY,
            Role::ADMIN_SM,
        ]);
    }

    /**
     * View a single franchise: superadmin/system_admin/system_admin_readonly always allowed;
     * admin_sm only if the franchise is theirs.
     */
    public function view(User $user, Franchise $franchise): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && (int) $user->sm_franchise_id === (int) $franchise->id;
    }

    /**
     * Add a member (admin or client) to a franchise.
     * Superadmin/system_admin can add to any franchise; admin_sm only to their own.
     */
    public function addMember(User $user, Franchise $franchise): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && (int) $user->sm_franchise_id === (int) $franchise->id;
    }

    /**
     * Create a franchise: superadmin or system_admin.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }

    /**
     * Update franchise data (name, type, email, etc.): superadmin or system_admin.
     */
    public function update(User $user, Franchise $franchise): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }

    /**
     * Toggle franchise active/inactive status: superadmin or system_admin.
     */
    public function toggleStatus(User $user, Franchise $franchise): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }

    /**
     * Delete a franchise: superadmin or system_admin.
     */
    public function delete(User $user, Franchise $franchise): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }
}

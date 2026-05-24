<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;

class FranchisePolicy
{
    /**
     * List franchises: system roles see all, admin_sm sees theirs,
     * sub_franchise_owner/sub_franchise_admin see their sub-franchise.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::SYSTEM_ADMIN_READONLY,
            Role::ADMIN_SM,
            Role::SUB_FRANCHISE_OWNER,
            Role::SUB_FRANCHISE_ADMIN,
        ]);
    }

    /**
     * View a single franchise: system roles always allowed; admin_sm only theirs;
     * sub_franchise_owner/sub_franchise_admin only their sub-franchise.
     */
    public function view(User $user, Franchise $franchise): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])) {
            return true;
        }

        if ($user->hasRole(Role::ADMIN_SM)) {
            return (int) $user->sm_franchise_id === (int) $franchise->id;
        }

        if ($user->hasAnyRole([Role::SUB_FRANCHISE_OWNER, Role::SUB_FRANCHISE_ADMIN])) {
            return $user->sub_franchise_id !== null
                && (int) $user->sub_franchise_id === (int) $franchise->id;
        }

        return false;
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
     * Create a franchise: superadmin/system_admin only.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }

    /**
     * Update franchise data (name, type, email, etc.): superadmin/system_admin always;
     * admin_sm only for their own franchise.
     */
    public function update(User $user, Franchise $franchise): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && (int) $user->sm_franchise_id === (int) $franchise->id;
    }

    /**
     * Toggle franchise active/inactive status: superadmin/system_admin always;
     * admin_sm only for their own franchise.
     */
    public function toggleStatus(User $user, Franchise $franchise): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && (int) $user->sm_franchise_id === (int) $franchise->id;
    }

    /**
     * Delete a franchise: superadmin/system_admin only.
     */
    public function delete(User $user, Franchise $franchise): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }
}

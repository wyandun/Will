<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;

class FranchisePolicy
{
    /**
     * List franchises: superadmin sees all, admin_sm sees theirs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::SUPERADMIN) || $user->hasRole(Role::ADMIN_SM);
    }

    /**
     * View a single franchise: superadmin always allowed;
     * admin_sm only if the franchise is theirs.
     */
    public function view(User $user, Franchise $franchise): bool
    {
        if ($user->hasRole(Role::SUPERADMIN)) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && (int) $user->sm_franchise_id === (int) $franchise->id;
    }

    /**
     * Create a franchise: superadmin only.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }

    /**
     * Update a franchise: superadmin only.
     */
    public function update(User $user, Franchise $franchise): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }

    /**
     * Delete a franchise: superadmin only.
     */
    public function delete(User $user, Franchise $franchise): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }
}

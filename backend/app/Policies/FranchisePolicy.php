<?php

namespace App\Policies;

use App\Models\Franchise;
use App\Models\User;

class FranchisePolicy
{
    /**
     * List franchises: superadmin sees all, admin_sm sees theirs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->hasRole('admin_sm');
    }

    /**
     * View a single franchise: superadmin always allowed;
     * admin_sm only if the franchise is theirs.
     */
    public function view(User $user, Franchise $franchise): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $user->hasRole('admin_sm')
            && $user->sm_franchise_id === $franchise->id;
    }

    /**
     * Create a franchise: superadmin only.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Update a franchise: superadmin only.
     */
    public function update(User $user, Franchise $franchise): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Delete a franchise: superadmin only.
     */
    public function delete(User $user, Franchise $franchise): bool
    {
        return $user->hasRole('superadmin');
    }
}

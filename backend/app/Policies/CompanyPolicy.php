<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * List companies: superadmin sees all, admin_sm sees theirs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->hasRole('admin_sm');
    }

    /**
     * View a single company: superadmin always allowed;
     * admin_sm only if the company belongs to their franchise.
     */
    public function view(User $user, Company $company): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $user->hasRole('admin_sm')
            && (int) $user->sm_franchise_id === (int) $company->sm_franchise_id;
    }

    /**
     * Create a company: superadmin or admin_sm (within their franchise).
     */
    public function create(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->hasRole('admin_sm');
    }

    /**
     * Update a company: superadmin or admin_sm (only their franchise's companies).
     */
    public function update(User $user, Company $company): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $user->hasRole('admin_sm')
            && (int) $user->sm_franchise_id === (int) $company->sm_franchise_id;
    }

    /**
     * Delete a company: superadmin or admin_sm (only their franchise's companies).
     */
    public function delete(User $user, Company $company): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $user->hasRole('admin_sm')
            && (int) $user->sm_franchise_id === (int) $company->sm_franchise_id;
    }
}

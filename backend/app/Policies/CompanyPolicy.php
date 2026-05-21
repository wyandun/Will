<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CompanyPolicy
{
    /**
     * List companies: superadmin sees all, admin_sm sees theirs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::SUPERADMIN) || $user->hasRole(Role::ADMIN_SM);
    }

    /**
     * View a single company: superadmin always allowed;
     * admin_sm only if the company belongs to their franchise.
     */
    public function view(User $user, Company $company): bool
    {
        if ($user->hasRole(Role::SUPERADMIN)) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && (int) $user->sm_franchise_id === (int) $company->sm_franchise_id;
    }

    /**
     * Create a company: superadmin always; admin_sm only if assigned to a franchise.
     */
    public function create(User $user): Response
    {
        if ($user->hasRole(Role::SUPERADMIN)) {
            return Response::allow();
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return Response::deny('policies.unauthorized');
        }

        if ($user->sm_franchise_id === null) {
            return Response::deny('policies.franchise_required');
        }

        return Response::allow();
    }

    /**
     * Update a company: superadmin or admin_sm (only their franchise's companies).
     */
    public function update(User $user, Company $company): bool
    {
        if ($user->hasRole(Role::SUPERADMIN)) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && (int) $user->sm_franchise_id === (int) $company->sm_franchise_id;
    }

    /**
     * Delete a company: superadmin or admin_sm (only their franchise's companies).
     */
    public function delete(User $user, Company $company): bool
    {
        if ($user->hasRole(Role::SUPERADMIN)) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && (int) $user->sm_franchise_id === (int) $company->sm_franchise_id;
    }
}

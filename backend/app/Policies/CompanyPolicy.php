<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CompanyPolicy
{
    /**
     * List companies: system roles see all, admin_sm sees their franchise's,
     * sb_owner/sb_employee see only their own company.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::SYSTEM_ADMIN_READONLY,
            Role::ADMIN_SM,
            Role::SB_OWNER,
            Role::SB_EMPLOYEE,
        ]);
    }

    /**
     * View a single company: system roles always allowed; admin_sm only if the
     * company belongs to their franchise; sb_owner/sb_employee only their own.
     */
    public function view(User $user, Company $company): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])) {
            return true;
        }

        if ($user->hasRole(Role::ADMIN_SM)) {
            return (int) $user->sm_franchise_id === (int) $company->sm_franchise_id;
        }

        if ($user->hasAnyRole([Role::SB_OWNER, Role::SB_EMPLOYEE])) {
            return $user->company_id !== null
                && (int) $user->company_id === (int) $company->id;
        }

        return false;
    }

    /**
     * Create a company: superadmin/system_admin always; admin_sm only if assigned to a franchise.
     */
    public function create(User $user): Response
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
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
     * Update a company: superadmin/system_admin or admin_sm (only their franchise's companies).
     */
    public function update(User $user, Company $company): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && (int) $user->sm_franchise_id === (int) $company->sm_franchise_id;
    }

    /**
     * Delete a company: superadmin/system_admin or admin_sm (only their franchise's companies).
     */
    public function delete(User $user, Company $company): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && (int) $user->sm_franchise_id === (int) $company->sm_franchise_id;
    }
}

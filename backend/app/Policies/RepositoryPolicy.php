<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Repository;
use App\Models\User;

class RepositoryPolicy
{
    /**
     * List repositories.
     * superadmin/system_admin see all; admin_sm results are scoped at the service layer.
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
     * View a single repository.
     *
     * - superadmin / system_admin / system_admin_readonly → always allowed
     * - admin_sm → only for repositories whose company belongs to their franchise
     */
    public function view(User $user, Repository $repository): bool
    {
        if ($this->isSupervisorRole($user)) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        $company = $repository->company ?? Company::find($repository->company_id);

        return $company && (int) $company->sm_franchise_id === (int) $user->sm_franchise_id;
    }

    /**
     * Create a repository.
     *
     * - superadmin / system_admin → always allowed
     * - admin_sm → only on companies belonging to their franchise
     */
    public function create(User $user, int $companyId): bool
    {
        // Policy is the single gate: verify company exists even if validation already checked it
        if (! Company::where('id', $companyId)->exists()) {
            return false;
        }

        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        $company = Company::find($companyId);

        return $company && (int) $company->sm_franchise_id === (int) $user->sm_franchise_id;
    }

    /**
     * Upload documents to / modify a repository.
     * Same franchise-ownership scope as delete — excludes read-only roles.
     *
     * - superadmin / system_admin → always allowed
     * - admin_sm → only for repositories whose company belongs to their franchise
     */
    public function update(User $user, Repository $repository): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        $company = $repository->company ?? Company::find($repository->company_id);

        return $company && (int) $company->sm_franchise_id === (int) $user->sm_franchise_id;
    }

    /**
     * Delete a repository.
     *
     * - superadmin / system_admin → always allowed
     * - admin_sm → only for repositories whose company belongs to their franchise
     */
    public function delete(User $user, Repository $repository): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        $company = $repository->company;
        if ($company === null) {
            return false;
        }

        return (int) $company->sm_franchise_id === (int) $user->sm_franchise_id;
    }

    private function isSupervisorRole(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY]);
    }
}

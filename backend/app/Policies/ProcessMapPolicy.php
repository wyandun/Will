<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Company;
use App\Models\ProcessMap;
use App\Models\User;

class ProcessMapPolicy
{
    /**
     * Anyone with elevated visibility may list process maps.
     * admin_sm may list (results are scoped at the controller / filters layer).
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
     * View a single process map (and its full tree).
     *
     * - superadmin / system_admin / system_admin_readonly → always allowed
     * - admin_sm → only for maps whose company belongs to their franchise
     * - others   → denied
     */
    public function view(User $user, ProcessMap $map): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        $company = $map->company ?? Company::find($map->company_id);

        return $company && (int) $company->sm_franchise_id === (int) $user->sm_franchise_id;
    }

    /**
     * Create a process map.
     *
     * - superadmin / system_admin → always allowed
     * - system_admin_readonly    → denied
     * - admin_sm                 → only on companies belonging to their franchise
     * - others                   → denied
     */
    public function create(User $user, ?int $companyId = null): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        if ($companyId === null) {
            return false;
        }

        $company = Company::find($companyId);
        if ($company === null) {
            return false;
        }

        return (int) $company->sm_franchise_id === (int) $user->sm_franchise_id;
    }

    /**
     * Delete a process map.
     *
     * - superadmin / system_admin → always allowed
     * - admin_sm                 → only for maps whose company belongs to their franchise
     * - others                   → denied
     */
    public function delete(User $user, ProcessMap $map): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        $company = $map->company;
        if ($company === null) {
            return false;
        }

        return (int) $company->sm_franchise_id === (int) $user->sm_franchise_id;
    }
}

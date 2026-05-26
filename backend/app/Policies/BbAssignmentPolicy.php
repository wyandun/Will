<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\BbAssignment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BbAssignmentPolicy
{
    /**
     * Assign a BB to a company: superadmin/system_admin always allowed;
     * admin_sm only within their own franchise scope (enforced in the service).
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
     * Remove a BB assignment: superadmin/system_admin always allowed;
     * admin_sm only if the related company belongs to their franchise.
     */
    public function delete(User $user, BbAssignment $bbAssignment): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        // Load the company relationship if not already loaded.
        $company = $bbAssignment->company;

        return $company && (int) $user->sm_franchise_id === (int) $company->sm_franchise_id;
    }
}

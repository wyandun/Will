<?php

namespace App\Policies;

use App\Models\BbAssignment;
use App\Models\User;

class BbAssignmentPolicy
{
    /**
     * Assign a BB to a company: superadmin always allowed;
     * admin_sm only within their own franchise scope (enforced in the service).
     */
    public function create(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->hasRole('admin_sm');
    }

    /**
     * Remove a BB assignment: superadmin always allowed;
     * admin_sm only if the related company belongs to their franchise.
     */
    public function delete(User $user, BbAssignment $bbAssignment): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        if (! $user->hasRole('admin_sm')) {
            return false;
        }

        // Load the company relationship if not already loaded.
        $company = $bbAssignment->company;

        return $company && (int) $user->sm_franchise_id === (int) $company->sm_franchise_id;
    }
}

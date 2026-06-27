<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\AssessmentContact;
use App\Models\User;

class AssessmentContactPolicy
{
    /**
     * List assessment contacts: superadmin and admin_sm only.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::ADMIN_SM]);
    }

    /**
     * View a single assessment contact: superadmin always;
     * admin_sm only if the contact belongs to their franchise scope.
     * Since assessment_contacts has no direct franchise_id, we allow all admin_sm for now.
     */
    public function view(User $user, AssessmentContact $contact): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::ADMIN_SM]);
    }

    /**
     * Save an internal audit note on an assessment contact.
     * Only admin_sm (and superadmin) may do this — it is an internal operation.
     */
    public function updateAdminNote(User $user, AssessmentContact $contact): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::ADMIN_SM]);
    }
}

<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAnySystemAdmin(User $user): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }

    public function createSystemAdmin(User $user): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }

    public function updateSystemAdmin(User $user, User $model): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }

    public function deleteSystemAdmin(User $user, User $model): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }

    /**
     * superadmin and admin_sm can send / manage invitations.
     */
    public function inviteUsers(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::ADMIN_SM]);
    }

    /**
     * Actor can manage (resend / revoke) a specific pending invitation.
     * Superadmin can act on any invitation; admin_sm only on invitations
     * belonging to their own franchise.
     */
    public function manageInvitation(User $authUser, User $target): bool
    {
        if ($authUser->hasRole(Role::SUPERADMIN)) {
            return true;
        }

        if ($authUser->hasRole(Role::ADMIN_SM)) {
            return $authUser->sm_franchise_id === $target->sm_franchise_id;
        }

        return false;
    }
}

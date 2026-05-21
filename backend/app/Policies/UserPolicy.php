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
     * superadmin, system_admin, and system_admin_readonly can view invitations.
     * system_admin can also send/manage invitations.
     * admin_sm can send/manage invitations for their franchise.
     */
    public function inviteUsers(User $user): bool
    {
        return $user->hasAnyRole([
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::SYSTEM_ADMIN_READONLY,
            Role::ADMIN_SM,
        ]);
    }

    /**
     * Actor can manage (resend / revoke) a specific pending invitation.
     * Superadmin/system_admin can act on any invitation;
     * admin_sm only on invitations belonging to their own franchise.
     * system_admin_readonly cannot manage (write) invitations.
     */
    public function manageInvitation(User $authUser, User $target): bool
    {
        if ($authUser->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        if ($authUser->hasRole(Role::ADMIN_SM)) {
            return $authUser->sm_franchise_id === $target->sm_franchise_id;
        }

        return false;
    }
}

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
     * Roles that can access the invitation system (list, send, resend, revoke).
     *
     * SYSTEM_ADMIN_READONLY can list invitations but cannot send — blocked by
     * Role::invitableByRole() returning [] in SendInvitationRequest validation.
     * SB_OWNER and SUB_FRANCHISE_OWNER can invite subordinate roles only
     * (sb_employee and sub_franchise_admin respectively).
     */
    public function inviteUsers(User $user): bool
    {
        return $user->hasAnyRole([
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::SYSTEM_ADMIN_READONLY,
            Role::ADMIN_SM,
            Role::SB_OWNER,
            Role::SUB_FRANCHISE_OWNER,
        ]);
    }

    // ── Franchise admin management (superadmin only) ──────────────────────────

    public function updateFranchiseAdmin(User $user, User $admin): bool
    {
        if (! $admin->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        return $user->hasRole(Role::SUPERADMIN);
    }

    public function deleteFranchiseAdmin(User $user, User $admin): bool
    {
        if (! $admin->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        return $user->hasRole(Role::SUPERADMIN);
    }

    public function restoreFranchiseAdmin(User $user): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }

    public function updateFranchiseAdminPermissions(User $user, User $admin): bool
    {
        if (! $admin->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        return $user->hasRole(Role::SUPERADMIN);
    }

    // ── Invitations ─────────────────────────────────────────────────────────────

    /**
     * Actor can manage (resend / revoke) a specific pending invitation.
     * Superadmin/system_admin can act on any invitation; admin_sm only on invitations
     * belonging to their own franchise.
     */
    public function manageInvitation(User $authUser, User $target): bool
    {
        if ($authUser->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        if ($authUser->hasRole(Role::ADMIN_SM)) {
            return $authUser->sm_franchise_id === $target->sm_franchise_id;
        }

        if ($authUser->hasRole(Role::SB_OWNER)) {
            return $authUser->company_id !== null
                && $authUser->company_id === $target->company_id;
        }

        if ($authUser->hasRole(Role::SUB_FRANCHISE_OWNER)) {
            return $authUser->sub_franchise_id !== null
                && $authUser->sub_franchise_id === $target->sub_franchise_id;
        }

        return false;
    }
}

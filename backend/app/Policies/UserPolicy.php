<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * Management of the System Admins roster is intentionally superadmin-only.
     * This is a privilege-escalation guardrail: a system_admin must not be able
     * to enumerate, create, or remove other system_admin / system_admin_readonly
     * accounts. "Read parity with superadmin" applies to business/tenant data
     * (franchises, companies, dashboards, process maps), NOT to the meta-admin
     * roster, which only superadmin manages.
     */
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
     * superadmin, system_admin, system_admin_readonly (read), and admin_sm can access invitations.
     */
    public function inviteUsers(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY, Role::ADMIN_SM]);
    }

    // ── Franchise admin management (superadmin + system_admin) ────────────────

    public function updateFranchiseAdmin(User $user, User $admin): bool
    {
        if (! $admin->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }

    public function deleteFranchiseAdmin(User $user, User $admin): bool
    {
        if (! $admin->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }

    public function restoreFranchiseAdmin(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }

    public function updateFranchiseAdminPermissions(User $user, User $admin): bool
    {
        if (! $admin->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }

    /**
     * Separated from updateFranchiseAdminPermissions to decouple read and write
     * authorization. The read path additionally allows system_admin_readonly
     * (view-only parity with superadmin), while the write path does not.
     */
    public function viewFranchiseAdminPermissions(User $user, User $admin): bool
    {
        if (! $admin->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        return $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY]);
    }

    // ── Franchise client management (superadmin + admin_sm) ─────────────────────

    public function updateFranchiseClient(User $user, User $client): bool
    {
        if (! $client->hasAnyRole([Role::SB_OWNER, Role::BB_EMPLOYEE])) {
            return false;
        }

        if ($user->hasRole(Role::SUPERADMIN)) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && $user->sm_franchise_id === $client->sm_franchise_id;
    }

    public function deleteFranchiseClient(User $user, User $client): bool
    {
        if (! $client->hasAnyRole([Role::SB_OWNER, Role::BB_EMPLOYEE])) {
            return false;
        }

        if ($user->hasRole(Role::SUPERADMIN)) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && $user->sm_franchise_id === $client->sm_franchise_id;
    }

    public function restoreFranchiseClient(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::ADMIN_SM]);
    }

    public function viewFranchiseClientPermissions(User $user, User $client): bool
    {
        if (! $client->hasAnyRole([Role::SB_OWNER, Role::BB_EMPLOYEE])) {
            return false;
        }

        if ($user->hasRole(Role::SUPERADMIN)) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && $user->sm_franchise_id === $client->sm_franchise_id;
    }

    public function updateFranchiseClientPermissions(User $user, User $client): bool
    {
        if (! $client->hasAnyRole([Role::SB_OWNER, Role::BB_EMPLOYEE])) {
            return false;
        }

        if ($user->hasRole(Role::SUPERADMIN)) {
            return true;
        }

        return $user->hasRole(Role::ADMIN_SM)
            && $user->sm_franchise_id === $client->sm_franchise_id;
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

        return false;
    }
}

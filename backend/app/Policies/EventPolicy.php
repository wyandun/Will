<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * All authenticated users can list events.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Visibility-aware: public events are visible to all, franchise events
     * to same-franchise users, private events only to the creator or superadmin/system_admin.
     */
    public function view(User $user, Event $event): bool
    {
        return match ($event->visibility) {
            'public' => true,
            'franchise' => $user->sm_franchise_id !== null
                && $user->sm_franchise_id === $event->creator->sm_franchise_id,
            'private' => (int) $user->id === (int) $event->user_id
                || $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]),
            default => false,
        };
    }

    /**
     * Superadmin, system_admin, admin_sm (with franchise), or sb_owner (with company)
     * can create events.
     */
    public function create(User $user): Response
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return Response::allow();
        }

        if ($user->hasRole(Role::ADMIN_SM)) {
            return $user->sm_franchise_id !== null
                ? Response::allow()
                : Response::deny('policies.franchise_required');
        }

        if ($user->hasRole(Role::SB_OWNER)) {
            return $user->company_id !== null
                ? Response::allow()
                : Response::deny('policies.company_required');
        }

        return Response::deny('policies.unauthorized');
    }

    /**
     * Only the event creator, superadmin, or system_admin can update.
     */
    public function update(User $user, Event $event): bool
    {
        return (int) $user->id === (int) $event->user_id
            || $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }

    /**
     * Only the event creator, superadmin, or system_admin can delete.
     */
    public function delete(User $user, Event $event): bool
    {
        return (int) $user->id === (int) $event->user_id
            || $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN]);
    }
}

<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Event;
use App\Models\User;

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
     * to same-franchise users, private events only to the creator or superadmin.
     */
    public function view(User $user, Event $event): bool
    {
        return match ($event->visibility) {
            'public' => true,
            'franchise' => $user->sm_franchise_id !== null
                && $user->sm_franchise_id === $event->creator->sm_franchise_id,
            'private' => (int) $user->id === (int) $event->user_id
                || $user->hasRole(Role::SUPERADMIN),
            default => false,
        };
    }

    /**
     * All authenticated users can create events.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the event creator or a superadmin can update.
     */
    public function update(User $user, Event $event): bool
    {
        return (int) $user->id === (int) $event->user_id
            || $user->hasRole(Role::SUPERADMIN);
    }

    /**
     * Only the event creator or a superadmin can delete.
     */
    public function delete(User $user, Event $event): bool
    {
        return (int) $user->id === (int) $event->user_id
            || $user->hasRole(Role::SUPERADMIN);
    }
}

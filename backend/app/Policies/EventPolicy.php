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
     * All authenticated users can view any event.
     */
    public function view(User $user, Event $event): bool
    {
        return true;
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

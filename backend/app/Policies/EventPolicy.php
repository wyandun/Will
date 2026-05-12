<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        if ($user->id === $event->user_id) {
            return true;
        }

        if ($user->hasRole(['superadmin', 'system_admin'])) {
            return true;
        }

        if ($event->visibility === 'public') {
            return true;
        }

        if ($event->visibility === 'franchise' && $user->hasRole('admin_sm') && $user->sm_franchise_id) {
            $owner = User::find($event->user_id);

            return $owner !== null && $owner->sm_franchise_id === $user->sm_franchise_id;
        }

        return false;
    }

    public function update(User $user, Event $event): bool
    {
        return $user->id === $event->user_id
            || $user->hasRole(['superadmin', 'system_admin']);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->id === $event->user_id
            || $user->hasRole(['superadmin', 'system_admin']);
    }
}

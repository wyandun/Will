<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserSearchService
{
    /**
     * Lightweight user lookup for the "Add Guests" search in the calendar event modal.
     *
     * Scoping:
     *   - superadmin / system_admin / system_admin_readonly: all users
     *   - everyone else: only users in their own franchise (sm_franchise_id match)
     *
     * Returns up to 10 results — the UI is for picking, not browsing.
     *
     * @return Collection<int, User>
     */
    public function search(User $authUser, string $q): Collection
    {
        $q = trim($q);

        $query = User::query()
            ->select(['id', 'name', 'email', 'avatar_path', 'sm_franchise_id'])
            ->whereNotNull('invitation_accepted_at')
            ->where('id', '!=', $authUser->id);

        if (! $authUser->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])) {
            $query->where('sm_franchise_id', $authUser->sm_franchise_id);
        }

        if ($q !== '') {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $q);
            $query->where(function ($qq) use ($escaped) {
                $qq->where('name', 'like', "%{$escaped}%")
                    ->orWhere('email', 'like', "%{$escaped}%");
            });
        }

        return $query->orderBy('name')->limit(10)->get();
    }
}

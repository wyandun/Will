<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CatalogItem;
use App\Models\User;

class CatalogItemPolicy
{
    /**
     * The catalog is the Strategic Mates service showcase and is fully
     * managed by the superadmin role only.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }

    public function view(User $user, CatalogItem $item): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }

    public function update(User $user, CatalogItem $item): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }

    public function delete(User $user, CatalogItem $item): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }
}

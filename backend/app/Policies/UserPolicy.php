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
}

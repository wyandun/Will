<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Only superadmin and admin_sm can create or view projects.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::ADMIN_SM]);
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->hasRole(Role::SUPERADMIN)) {
            return true;
        }

        if ($user->hasRole(Role::ADMIN_SM)) {
            return (int) $user->sm_franchise_id === $project->franchise_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::SUPERADMIN, Role::ADMIN_SM]);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasRole(Role::SUPERADMIN);
    }
}

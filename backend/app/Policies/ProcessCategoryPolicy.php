<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Company;
use App\Models\ProcessCategory;
use App\Models\ProcessMap;
use App\Models\User;

class ProcessCategoryPolicy
{
    public function view(User $user, ProcessCategory $category): bool
    {
        $map = $this->resolveMap($category);

        if ($map === null) {
            return false;
        }

        return $this->canAccess($user, $map);
    }

    public function update(User $user, ProcessCategory $category): bool
    {
        $map = $this->resolveMap($category);

        if ($map === null) {
            return false;
        }

        return $this->canWrite($user, $map);
    }

    private function resolveMap(ProcessCategory $category): ?ProcessMap
    {
        if ($category->processMap instanceof ProcessMap) {
            return $category->processMap;
        }

        /** @var ProcessMap|null */
        return ProcessMap::find($category->process_map_id);
    }

    private function canAccess(User $user, ProcessMap $map): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        $company = $this->resolveCompany($map);

        return $company && (int) $company->sm_franchise_id === (int) $user->sm_franchise_id;
    }

    private function canWrite(User $user, ProcessMap $map): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        $company = $this->resolveCompany($map);

        return $company && (int) $company->sm_franchise_id === (int) $user->sm_franchise_id;
    }

    private function resolveCompany(ProcessMap $map): ?Company
    {
        if ($map->company instanceof Company) {
            return $map->company;
        }

        /** @var Company|null */
        return Company::find($map->company_id);
    }
}

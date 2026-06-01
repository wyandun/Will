<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\ProcessMap;
use App\Models\SubProcess;
use App\Models\SubSubProcess;
use App\Models\User;

class SubSubProcessPolicy
{
    public function view(User $user, SubSubProcess $subSubProcess): bool
    {
        $map = $this->resolveMap($subSubProcess);

        if ($map === null) {
            return false;
        }

        return $this->canAccess($user, $map);
    }

    public function create(User $user, SubProcess $subProcess): bool
    {
        $map = $this->resolveMapFromSubProcess($subProcess);

        if ($map === null) {
            return false;
        }

        return $this->canWrite($user, $map);
    }

    public function update(User $user, SubSubProcess $subSubProcess): bool
    {
        $map = $this->resolveMap($subSubProcess);

        if ($map === null) {
            return false;
        }

        return $this->canWrite($user, $map);
    }

    public function delete(User $user, SubSubProcess $subSubProcess): bool
    {
        $map = $this->resolveMap($subSubProcess);

        if ($map === null) {
            return false;
        }

        return $this->canWrite($user, $map);
    }

    private function resolveMap(SubSubProcess $subSubProcess): ?ProcessMap
    {
        $subProcess = $subSubProcess->subProcess instanceof SubProcess
            ? $subSubProcess->subProcess
            : SubProcess::find($subSubProcess->sub_process_id);

        if (! $subProcess instanceof SubProcess) {
            return null;
        }

        return $this->resolveMapFromSubProcess($subProcess);
    }

    private function resolveMapFromSubProcess(SubProcess $subProcess): ?ProcessMap
    {
        $process = $subProcess->process instanceof Process
            ? $subProcess->process
            : Process::find($subProcess->process_id);

        if (! $process instanceof Process) {
            return null;
        }

        $category = $process->category instanceof ProcessCategory
            ? $process->category
            : ProcessCategory::find($process->category_id);

        if (! $category instanceof ProcessCategory) {
            return null;
        }

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

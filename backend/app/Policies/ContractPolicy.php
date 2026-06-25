<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    /**
     * List contracts. Reads include readonly admins and bb_employee
     * (results are scoped at the service layer).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::SYSTEM_ADMIN_READONLY,
            Role::ADMIN_SM,
            Role::BB_EMPLOYEE,
        ]);
    }

    /**
     * View a single contract.
     *
     * - superadmin / system_admin / readonly → always
     * - admin_sm → only contracts whose company belongs to their franchise
     * - bb_employee → only contracts of their own company
     * - others → denied
     */
    public function view(User $user, Contract $contract): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])) {
            return true;
        }

        if ($user->hasRole(Role::ADMIN_SM)) {
            return $this->companyInFranchise($contract, $user);
        }

        if ($user->hasRole(Role::BB_EMPLOYEE)) {
            return $contract->company_id === $user->company_id;
        }

        return false;
    }

    /**
     * Create a contract. Writes exclude readonly admins and bb_employee.
     */
    public function create(User $user, ?int $companyId = null): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        if ($companyId === null) {
            return false;
        }

        $company = Company::find($companyId);

        return $company !== null
            && (int) $company->sm_franchise_id === (int) $user->sm_franchise_id;
    }

    public function update(User $user, Contract $contract): bool
    {
        return $this->canWrite($user, $contract);
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $this->canWrite($user, $contract);
    }

    public function send(User $user, Contract $contract): bool
    {
        return $this->canWrite($user, $contract);
    }

    public function sync(User $user, Contract $contract): bool
    {
        return $this->canWrite($user, $contract);
    }

    /**
     * Write gate: superadmin / system_admin always; admin_sm only within
     * their franchise; everyone else (readonly, bb, clients) denied.
     */
    private function canWrite(User $user, Contract $contract): bool
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN])) {
            return true;
        }

        if (! $user->hasRole(Role::ADMIN_SM)) {
            return false;
        }

        return $this->companyInFranchise($contract, $user);
    }

    private function companyInFranchise(Contract $contract, User $user): bool
    {
        $company = $contract->company instanceof Company
            ? $contract->company
            : Company::find($contract->company_id);

        return $company !== null
            && (int) $company->sm_franchise_id === (int) $user->sm_franchise_id;
    }
}

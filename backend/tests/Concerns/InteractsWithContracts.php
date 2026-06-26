<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Shared helpers for the contracts test suite.
 *
 * Each user is created with a role AND its module permissions synced, so the
 * `module.permission:contracts` middleware behaves exactly as in production
 * (readonly/bb get can_read only; admin_sm/superadmin/system_admin get write).
 */
trait InteractsWithContracts
{
    private function contractUserWithRole(string $role, array $attributes = []): User
    {
        SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create($attributes);
        $user->assignRole($role);
        UserPermission::syncForRole($user->id, $role);

        return $user;
    }

    private function createSuperadmin(): User
    {
        return $this->contractUserWithRole(Role::SUPERADMIN);
    }

    private function createSystemAdmin(): User
    {
        return $this->contractUserWithRole(Role::SYSTEM_ADMIN);
    }

    private function createReadonlyAdmin(): User
    {
        return $this->contractUserWithRole(Role::SYSTEM_ADMIN_READONLY);
    }

    private function createAdminSm(Franchise $franchise): User
    {
        return $this->contractUserWithRole(Role::ADMIN_SM, ['sm_franchise_id' => $franchise->id]);
    }

    private function createBbEmployee(Company $company): User
    {
        return $this->contractUserWithRole(Role::BB_EMPLOYEE, [
            'sm_franchise_id' => $company->sm_franchise_id,
            'company_id' => $company->id,
        ]);
    }

    private function createCompany(?Franchise $franchise = null): Company
    {
        $franchise ??= Franchise::factory()->create();

        return Company::create([
            'name' => 'Company '.uniqid(),
            'sm_franchise_id' => $franchise->id,
        ]);
    }

    /**
     * A client user (sb_owner) attached to a company within a franchise.
     */
    private function createClientUser(Franchise $franchise, Company $company): User
    {
        return $this->contractUserWithRole(Role::SB_OWNER, [
            'sm_franchise_id' => $franchise->id,
            'company_id' => $company->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createContract(Company $company, User $client, array $attributes = []): Contract
    {
        return Contract::create(array_merge([
            'company_id' => $company->id,
            'client_user_id' => $client->id,
            'title' => 'Contract '.uniqid(),
            'status' => Contract::STATUS_DRAFT,
        ], $attributes));
    }
}

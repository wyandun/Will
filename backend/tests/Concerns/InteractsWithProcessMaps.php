<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Franchise;
use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\ProcessMap;
use App\Models\SubProcess;
use App\Models\User;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Shared helpers for the process maps test suite (auth roles + tree fixtures).
 *
 * Roles are already seeded by Tests\TestCase::setUp(); these helpers only
 * assign them. The DB-level firstOrCreate is kept for safety/parity with the
 * pre-existing ProcessMapTest helpers.
 */
trait InteractsWithProcessMaps
{
    private function userWithRole(string $role, array $attributes = []): User
    {
        SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function createSuperadmin(): User
    {
        return $this->userWithRole(Role::SUPERADMIN);
    }

    private function createSystemAdmin(): User
    {
        return $this->userWithRole(Role::SYSTEM_ADMIN);
    }

    private function createReadonlyAdmin(): User
    {
        return $this->userWithRole(Role::SYSTEM_ADMIN_READONLY);
    }

    private function createAdminSm(Franchise $franchise): User
    {
        return $this->userWithRole(Role::ADMIN_SM, ['sm_franchise_id' => $franchise->id]);
    }

    private function createSbOwner(?Franchise $franchise = null): User
    {
        return $this->userWithRole(Role::SB_OWNER, ['sm_franchise_id' => $franchise?->id]);
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
     * Build a process map with the 3 fixed divisions (STRATEGIC, VALUE CHAIN, SUPPORT).
     */
    private function createMapWithCategories(Company $company, string $type = 'franquiciadora'): ProcessMap
    {
        $map = ProcessMap::factory()->forCompany($company)->create(['type' => $type]);

        $map->categories()->createMany([
            ['type' => ProcessCategory::TYPE_STRATEGIC, 'name_es' => 'Estratégicos', 'name_en' => 'Strategic', 'order_index' => 1],
            ['type' => ProcessCategory::TYPE_VALUE_CHAIN, 'name_es' => 'Cadena de Valor', 'name_en' => 'Value Chain', 'order_index' => 2],
            ['type' => ProcessCategory::TYPE_SUPPORT, 'name_es' => 'Apoyo', 'name_en' => 'Support', 'order_index' => 3],
        ]);

        return $map->fresh();
    }

    /**
     * Return a category of the given fixed type from a map.
     */
    private function categoryOfType(ProcessMap $map, string $type): ProcessCategory
    {
        return $map->categories()->where('type', $type)->firstOrFail();
    }

    /**
     * Build a full Map → Category → Process → SubProcess → SubSubProcess chain.
     * Returns the leaf SubProcess for convenience.
     */
    private function buildFullTree(Company $company): SubProcess
    {
        $map = $this->createMapWithCategories($company);
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_VALUE_CHAIN);

        $process = Process::factory()->create(['category_id' => $category->id, 'code' => 'OPS']);

        return SubProcess::factory()->create([
            'process_id' => $process->id,
            'code' => 'OPS-P01',
        ]);
    }
}

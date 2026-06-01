<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Franchise;
use App\Models\ProcessMap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class ProcessMapTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function createSuperadmin(): User
    {
        SpatieRole::firstOrCreate(['name' => Role::SUPERADMIN, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole(Role::SUPERADMIN);

        return $user;
    }

    private function createAdminSm(Franchise $franchise): User
    {
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);
        $user = User::factory()->create(['sm_franchise_id' => $franchise->id]);
        $user->assignRole(Role::ADMIN_SM);

        return $user;
    }

    private function createCompany(?Franchise $franchise = null): Company
    {
        $franchise ??= Franchise::factory()->create();

        return Company::create([
            'name' => 'Company '.uniqid(),
            'sm_franchise_id' => $franchise->id,
        ]);
    }

    // ===========================================================================
    // Tests
    // ===========================================================================

    public function test_close_deal_still_creates_two_process_maps(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/companies/close-deal', [
            'name' => 'Deal Co',
            'sm_franchise_id' => $franchise->id,
        ]);

        $response->assertStatus(201);

        $companyId = (int) $response->json('data.id');

        $this->assertSame(2, ProcessMap::where('company_id', $companyId)->count());
        $this->assertDatabaseHas('process_maps', [
            'company_id' => $companyId,
            'type' => 'franquiciadora',
        ]);
        $this->assertDatabaseHas('process_maps', [
            'company_id' => $companyId,
            'type' => 'franquiciada',
        ]);
    }

    public function test_superadmin_can_create_a_third_process_map_on_a_company(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);

        // Seed the two auto-created maps to simulate post-Close-Deal state.
        ProcessMap::create(['company_id' => $company->id, 'type' => 'franquiciadora', 'name_es' => 'A', 'name_en' => 'A']);
        ProcessMap::create(['company_id' => $company->id, 'type' => 'franquiciada', 'name_es' => 'B', 'name_en' => 'B']);

        $response = $this->actingAs($superadmin)->postJson('/api/v1/process-maps', [
            'company_id' => $company->id,
            'type' => 'custom',
            'name_es' => 'Mapa Custom',
            'name_en' => 'Custom Map',
            'description' => 'Tercero',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name_es', 'Mapa Custom');
        $response->assertJsonPath('data.type', 'custom');

        $this->assertSame(3, ProcessMap::where('company_id', $company->id)->count());
    }

    public function test_admin_sm_can_create_map_on_company_in_own_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);
        $company = $this->createCompany($franchise);

        $response = $this->actingAs($admin)->postJson('/api/v1/process-maps', [
            'company_id' => $company->id,
            'type' => 'extra',
            'name_es' => 'Mapa Extra',
            'name_en' => 'Extra Map',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('process_maps', [
            'company_id' => $company->id,
            'type' => 'extra',
        ]);
    }

    public function test_admin_sm_cannot_create_map_on_company_in_other_franchise(): void
    {
        $ownFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($ownFranchise);
        $otherCompany = $this->createCompany($otherFranchise);

        $response = $this->actingAs($admin)->postJson('/api/v1/process-maps', [
            'company_id' => $otherCompany->id,
            'type' => 'extra',
            'name_es' => 'Mapa Extra',
            'name_en' => 'Extra Map',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('process_maps', [
            'company_id' => $otherCompany->id,
            'type' => 'extra',
        ]);
    }

    public function test_index_filters_by_company_and_franchise(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchiseA = Franchise::factory()->create();
        $franchiseB = Franchise::factory()->create();
        $companyA = $this->createCompany($franchiseA);
        $companyB = $this->createCompany($franchiseB);

        ProcessMap::create(['company_id' => $companyA->id, 'type' => 't1', 'name_es' => 'A1', 'name_en' => 'A1']);
        ProcessMap::create(['company_id' => $companyA->id, 'type' => 't2', 'name_es' => 'A2', 'name_en' => 'A2']);
        ProcessMap::create(['company_id' => $companyB->id, 'type' => 't1', 'name_es' => 'B1', 'name_en' => 'B1']);

        // Filter by company
        $byCompany = $this->actingAs($superadmin)->getJson('/api/v1/process-maps?company_id='.$companyA->id);
        $byCompany->assertStatus(200);
        $this->assertCount(2, $byCompany->json('data'));

        // Filter by franchise
        $byFranchise = $this->actingAs($superadmin)->getJson('/api/v1/process-maps?franchise_id='.$franchiseB->id);
        $byFranchise->assertStatus(200);
        $this->assertCount(1, $byFranchise->json('data'));
        $this->assertSame($companyB->id, $byFranchise->json('data.0.company_id'));
    }

    public function test_superadmin_can_delete_a_process_map(): void
    {
        $superadmin = $this->createSuperadmin();
        $company = $this->createCompany();
        $map = ProcessMap::create([
            'company_id' => $company->id,
            'type' => 'goner',
            'name_es' => 'Borrar',
            'name_en' => 'Delete',
        ]);

        $response = $this->actingAs($superadmin)->deleteJson('/api/v1/process-maps/'.$map->id);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('process_maps', ['id' => $map->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Franchise;
use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\ProcessMap;
use App\Models\SubProcess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class ProcessMapTreeTest extends TestCase
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
        return Company::create([
            'name' => 'Company '.uniqid(),
            'sm_franchise_id' => $franchise?->id,
        ]);
    }

    private function createMapWithCategories(Company $company): ProcessMap
    {
        $map = ProcessMap::create([
            'company_id' => $company->id,
            'type' => 'franquiciadora',
            'name_es' => 'Mapa',
            'name_en' => 'Map',
        ]);

        $map->categories()->createMany([
            ['type' => ProcessCategory::TYPE_STRATEGIC, 'name_es' => 'Estratégicos', 'name_en' => 'Strategic', 'order_index' => 1],
            ['type' => ProcessCategory::TYPE_VALUE_CHAIN, 'name_es' => 'Cadena de Valor', 'name_en' => 'Value Chain', 'order_index' => 2],
            ['type' => ProcessCategory::TYPE_SUPPORT, 'name_es' => 'Apoyo', 'name_en' => 'Support', 'order_index' => 3],
        ]);

        return $map->fresh();
    }

    // ===========================================================================
    // Tests
    // ===========================================================================

    public function test_show_returns_full_tree_with_three_categories(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $map = $this->createMapWithCategories($company);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/process-maps/'.$map->id);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $map->id);

        $categories = $response->json('data.categories');
        $this->assertCount(3, $categories);

        $types = array_column($categories, 'type');
        $this->assertContains(ProcessCategory::TYPE_STRATEGIC, $types);
        $this->assertContains(ProcessCategory::TYPE_VALUE_CHAIN, $types);
        $this->assertContains(ProcessCategory::TYPE_SUPPORT, $types);
    }

    public function test_admin_sm_can_create_process_in_own_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);
        $company = $this->createCompany($franchise);
        $map = $this->createMapWithCategories($company);

        $category = $map->categories()->where('type', ProcessCategory::TYPE_STRATEGIC)->first();

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/process-categories/'.$category->id.'/processes',
            ['code' => 'GTH', 'name_es' => 'Gestión', 'name_en' => 'Management']
        );

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.code', 'GTH');

        $this->assertDatabaseHas('processes', ['category_id' => $category->id, 'code' => 'GTH']);
    }

    public function test_admin_sm_cannot_create_process_in_other_franchise(): void
    {
        $ownFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($ownFranchise);
        $otherCompany = $this->createCompany($otherFranchise);
        $otherMap = $this->createMapWithCategories($otherCompany);

        $category = $otherMap->categories()->where('type', ProcessCategory::TYPE_STRATEGIC)->first();

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/process-categories/'.$category->id.'/processes',
            ['code' => 'GTH', 'name_es' => 'Gestión', 'name_en' => 'Management']
        );

        $response->assertStatus(403);
        $this->assertDatabaseMissing('processes', ['category_id' => $category->id]);
    }

    public function test_subprocess_code_auto_generates_with_pattern(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $map = $this->createMapWithCategories($company);

        $category = $map->categories()->where('type', ProcessCategory::TYPE_VALUE_CHAIN)->first();

        $process = Process::create([
            'category_id' => $category->id,
            'code' => 'XX',
            'name_es' => 'Proceso',
            'name_en' => 'Process',
            'order_index' => 1,
        ]);

        $response = $this->actingAs($superadmin)->postJson(
            '/api/v1/processes/'.$process->id.'/sub-processes',
            ['name_es' => 'Subproceso', 'name_en' => 'Subprocess']
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'XX-P01');
    }

    public function test_subsubprocess_code_auto_generates_with_pattern(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $map = $this->createMapWithCategories($company);

        $category = $map->categories()->where('type', ProcessCategory::TYPE_VALUE_CHAIN)->first();

        $process = Process::create([
            'category_id' => $category->id,
            'code' => 'XX',
            'name_es' => 'Proceso',
            'name_en' => 'Process',
            'order_index' => 1,
        ]);

        $subProcess = SubProcess::create([
            'process_id' => $process->id,
            'code' => 'XX-P01',
            'name_es' => 'Subproceso',
            'name_en' => 'Subprocess',
            'order_index' => 1,
        ]);

        $response = $this->actingAs($superadmin)->postJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/sub-sub-processes',
            ['name_es' => 'Sub-subproceso', 'name_en' => 'Sub-subprocess']
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'XX-P01-S01');
    }

    public function test_renaming_category_persists_bilingual_names(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $map = $this->createMapWithCategories($company);

        $category = $map->categories()->where('type', ProcessCategory::TYPE_STRATEGIC)->first();

        $response = $this->actingAs($superadmin)->patchJson(
            '/api/v1/process-categories/'.$category->id,
            ['name_es' => 'Dirección Estratégica', 'name_en' => 'Strategic Direction']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.name_es', 'Dirección Estratégica');
        $response->assertJsonPath('data.name_en', 'Strategic Direction');

        $this->assertDatabaseHas('process_categories', [
            'id' => $category->id,
            'name_es' => 'Dirección Estratégica',
            'name_en' => 'Strategic Direction',
        ]);
    }
}

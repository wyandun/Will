<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Models\ProcessCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

/**
 * Covers the "Edit Division" flow (PATCH /process-categories/{id}) and the
 * auto-seeding of the 3 fixed divisions (STRATEGIC, VALUE CHAIN, SUPPORT)
 * when a process map is created.
 */
class ProcessCategoryTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    public function test_creating_a_map_auto_seeds_the_three_fixed_divisions(): void
    {
        $superadmin = $this->createSuperadmin();
        $company = $this->createCompany();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/process-maps', [
            'company_id' => $company->id,
            'type' => 'custom',
            'name_es' => 'Mapa',
            'name_en' => 'Map',
        ]);

        $response->assertStatus(201);
        $mapId = (int) $response->json('data.id');

        $types = ProcessCategory::where('process_map_id', $mapId)->pluck('type')->all();
        $this->assertCount(3, $types);
        $this->assertContains(ProcessCategory::TYPE_STRATEGIC, $types);
        $this->assertContains(ProcessCategory::TYPE_VALUE_CHAIN, $types);
        $this->assertContains(ProcessCategory::TYPE_SUPPORT, $types);
    }

    public function test_rename_division_persists_bilingual_names(): void
    {
        $superadmin = $this->createSuperadmin();
        $map = $this->createMapWithCategories($this->createCompany());
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_STRATEGIC);

        $response = $this->actingAs($superadmin)->patchJson('/api/v1/process-categories/'.$category->id, [
            'name_es' => 'Dirección Estratégica',
            'name_en' => 'Strategic Direction',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name_es', 'Dirección Estratégica');
        $response->assertJsonPath('data.name_en', 'Strategic Direction');
        $this->assertDatabaseHas('process_categories', [
            'id' => $category->id,
            'name_en' => 'Strategic Direction',
        ]);
    }

    public function test_rename_division_requires_both_names(): void
    {
        $superadmin = $this->createSuperadmin();
        $map = $this->createMapWithCategories($this->createCompany());
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_SUPPORT);

        $response = $this->actingAs($superadmin)->patchJson('/api/v1/process-categories/'.$category->id, [
            'name_es' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name_es', 'name_en']);
    }

    public function test_admin_sm_can_rename_division_in_own_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);
        $map = $this->createMapWithCategories($this->createCompany($franchise));
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_VALUE_CHAIN);

        $response = $this->actingAs($admin)->patchJson('/api/v1/process-categories/'.$category->id, [
            'name_es' => 'Cadena',
            'name_en' => 'Chain',
        ]);

        $response->assertStatus(200);
    }

    public function test_admin_sm_cannot_rename_division_in_other_franchise(): void
    {
        $ownFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($ownFranchise);
        $otherMap = $this->createMapWithCategories($this->createCompany($otherFranchise));
        $category = $this->categoryOfType($otherMap, ProcessCategory::TYPE_STRATEGIC);

        $response = $this->actingAs($admin)->patchJson('/api/v1/process-categories/'.$category->id, [
            'name_es' => 'Hack',
            'name_en' => 'Hack',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('process_categories', [
            'id' => $category->id,
            'name_es' => 'Hack',
        ]);
    }
}

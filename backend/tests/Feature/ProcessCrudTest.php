<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Models\Process;
use App\Models\ProcessCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

/**
 * Covers macroprocess (Process) CRUD: create validation + code uniqueness,
 * update, delete, and admin_sm franchise scoping on writes.
 */
class ProcessCrudTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    public function test_update_process_changes_bilingual_names(): void
    {
        $superadmin = $this->createSuperadmin();
        $map = $this->createMapWithCategories($this->createCompany());
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_STRATEGIC);
        $process = Process::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($superadmin)->putJson('/api/v1/processes/'.$process->id, [
            'name_es' => 'Dirección',
            'name_en' => 'Direction',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name_es', 'Dirección');
        $response->assertJsonPath('data.name_en', 'Direction');
        $this->assertDatabaseHas('processes', [
            'id' => $process->id,
            'name_es' => 'Dirección',
        ]);
    }

    public function test_delete_process_removes_it(): void
    {
        $superadmin = $this->createSuperadmin();
        $map = $this->createMapWithCategories($this->createCompany());
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_STRATEGIC);
        $process = Process::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($superadmin)->deleteJson('/api/v1/processes/'.$process->id);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('processes', ['id' => $process->id]);
    }

    public function test_create_process_rejects_invalid_code_format(): void
    {
        $superadmin = $this->createSuperadmin();
        $map = $this->createMapWithCategories($this->createCompany());
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_STRATEGIC);

        // lowercase + too long: violates regex /^[A-Z]{2,4}$/
        $response = $this->actingAs($superadmin)->postJson(
            '/api/v1/process-categories/'.$category->id.'/processes',
            ['code' => 'abcde', 'name_es' => 'X', 'name_en' => 'X']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('code');
    }

    public function test_create_process_requires_code_and_names(): void
    {
        $superadmin = $this->createSuperadmin();
        $map = $this->createMapWithCategories($this->createCompany());
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_STRATEGIC);

        $response = $this->actingAs($superadmin)->postJson(
            '/api/v1/process-categories/'.$category->id.'/processes',
            []
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code', 'name_es', 'name_en']);
    }

    public function test_create_process_rejects_duplicate_code_within_same_map(): void
    {
        $superadmin = $this->createSuperadmin();
        $map = $this->createMapWithCategories($this->createCompany());
        $strategic = $this->categoryOfType($map, ProcessCategory::TYPE_STRATEGIC);
        $valueChain = $this->categoryOfType($map, ProcessCategory::TYPE_VALUE_CHAIN);

        Process::factory()->create(['category_id' => $strategic->id, 'code' => 'GTH']);

        // Same code, different division of the SAME map → rejected by the service.
        $response = $this->actingAs($superadmin)->postJson(
            '/api/v1/process-categories/'.$valueChain->id.'/processes',
            ['code' => 'GTH', 'name_es' => 'Gestión', 'name_en' => 'Management']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('code');
    }

    public function test_same_code_allowed_in_different_maps(): void
    {
        $superadmin = $this->createSuperadmin();
        $mapA = $this->createMapWithCategories($this->createCompany());
        $mapB = $this->createMapWithCategories($this->createCompany());
        $catA = $this->categoryOfType($mapA, ProcessCategory::TYPE_STRATEGIC);
        $catB = $this->categoryOfType($mapB, ProcessCategory::TYPE_STRATEGIC);

        Process::factory()->create(['category_id' => $catA->id, 'code' => 'GTH']);

        $response = $this->actingAs($superadmin)->postJson(
            '/api/v1/process-categories/'.$catB->id.'/processes',
            ['code' => 'GTH', 'name_es' => 'Gestión', 'name_en' => 'Management']
        );

        $response->assertStatus(201);
    }

    public function test_admin_sm_cannot_update_process_in_other_franchise(): void
    {
        $ownFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($ownFranchise);

        $otherMap = $this->createMapWithCategories($this->createCompany($otherFranchise));
        $category = $this->categoryOfType($otherMap, ProcessCategory::TYPE_STRATEGIC);
        $process = Process::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin)->putJson('/api/v1/processes/'.$process->id, [
            'name_es' => 'Hack',
            'name_en' => 'Hack',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_sm_can_delete_process_in_own_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);
        $map = $this->createMapWithCategories($this->createCompany($franchise));
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_STRATEGIC);
        $process = Process::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin)->deleteJson('/api/v1/processes/'.$process->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('processes', ['id' => $process->id]);
    }
}

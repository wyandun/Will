<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\SubProcess;
use App\Models\SubSubProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

/**
 * Covers sub-sub-process (3rd level) CRUD: sequential code generation
 * ({CODE}-S0N), update, delete, has_bpmn, and admin_sm scoping.
 */
class SubSubProcessCrudTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    private function makeSubProcess(?Franchise $franchise = null): SubProcess
    {
        $map = $this->createMapWithCategories($this->createCompany($franchise));
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_VALUE_CHAIN);
        $process = Process::factory()->create(['category_id' => $category->id, 'code' => 'AR']);

        return SubProcess::factory()->create(['process_id' => $process->id, 'code' => 'AR-P02']);
    }

    public function test_sub_sub_process_codes_increment_sequentially(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->makeSubProcess();

        $first = $this->actingAs($superadmin)->postJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/sub-sub-processes',
            ['name_es' => 'Uno', 'name_en' => 'One']
        );
        $second = $this->actingAs($superadmin)->postJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/sub-sub-processes',
            ['name_es' => 'Dos', 'name_en' => 'Two']
        );

        $first->assertStatus(201)->assertJsonPath('data.code', 'AR-P02-S01');
        $second->assertStatus(201)->assertJsonPath('data.code', 'AR-P02-S02');
    }

    public function test_create_sub_sub_process_requires_names(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->makeSubProcess();

        $response = $this->actingAs($superadmin)->postJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/sub-sub-processes',
            []
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name_es', 'name_en']);
    }

    public function test_update_sub_sub_process_changes_names(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->makeSubProcess();
        $leaf = SubSubProcess::factory()->create([
            'sub_process_id' => $subProcess->id,
            'code' => 'AR-P02-S01',
        ]);

        $response = $this->actingAs($superadmin)->putJson('/api/v1/sub-sub-processes/'.$leaf->id, [
            'name_es' => 'Actualizado',
            'name_en' => 'Updated',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name_es', 'Actualizado');
    }

    public function test_delete_sub_sub_process_removes_it(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->makeSubProcess();
        $leaf = SubSubProcess::factory()->create([
            'sub_process_id' => $subProcess->id,
            'code' => 'AR-P02-S01',
        ]);

        $response = $this->actingAs($superadmin)->deleteJson('/api/v1/sub-sub-processes/'.$leaf->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('sub_sub_processes', ['id' => $leaf->id]);
    }

    public function test_has_bpmn_flag_reflects_diagram_presence(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->makeSubProcess();
        $leaf = SubSubProcess::factory()->withBpmn()->create([
            'sub_process_id' => $subProcess->id,
            'code' => 'AR-P02-S01',
        ]);

        $response = $this->actingAs($superadmin)->putJson('/api/v1/sub-sub-processes/'.$leaf->id, [
            'name_es' => 'X',
            'name_en' => 'X',
        ]);

        $response->assertJsonPath('data.has_bpmn', true);
    }

    public function test_admin_sm_cannot_create_sub_sub_process_in_other_franchise(): void
    {
        $ownFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($ownFranchise);
        $subProcess = $this->makeSubProcess($otherFranchise);

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/sub-sub-processes',
            ['name_es' => 'Nope', 'name_en' => 'Nope']
        );

        $response->assertStatus(403);
    }
}

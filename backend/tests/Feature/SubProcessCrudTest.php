<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\SubProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

/**
 * Covers sub-process (process row) CRUD: sequential code generation
 * ({CODE}-P0N), update, delete, the has_bpmn / sub_sub_processes_count
 * resource fields, and admin_sm scoping.
 */
class SubProcessCrudTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    private function makeProcess(?Franchise $franchise = null): Process
    {
        $map = $this->createMapWithCategories($this->createCompany($franchise));
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_VALUE_CHAIN);

        return Process::factory()->create(['category_id' => $category->id, 'code' => 'DE']);
    }

    public function test_sub_process_codes_increment_sequentially(): void
    {
        $superadmin = $this->createSuperadmin();
        $process = $this->makeProcess();

        $first = $this->actingAs($superadmin)->postJson(
            '/api/v1/processes/'.$process->id.'/sub-processes',
            ['name_es' => 'Uno', 'name_en' => 'One']
        );
        $second = $this->actingAs($superadmin)->postJson(
            '/api/v1/processes/'.$process->id.'/sub-processes',
            ['name_es' => 'Dos', 'name_en' => 'Two']
        );

        $first->assertStatus(201)->assertJsonPath('data.code', 'DE-P01');
        $second->assertStatus(201)->assertJsonPath('data.code', 'DE-P02');
    }

    public function test_create_sub_process_requires_names(): void
    {
        $superadmin = $this->createSuperadmin();
        $process = $this->makeProcess();

        $response = $this->actingAs($superadmin)->postJson(
            '/api/v1/processes/'.$process->id.'/sub-processes',
            []
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name_es', 'name_en']);
    }

    public function test_update_sub_process_changes_names(): void
    {
        $superadmin = $this->createSuperadmin();
        $process = $this->makeProcess();
        $subProcess = SubProcess::factory()->create([
            'process_id' => $process->id,
            'code' => 'DE-P01',
        ]);

        $response = $this->actingAs($superadmin)->putJson('/api/v1/sub-processes/'.$subProcess->id, [
            'name_es' => 'Actualizado',
            'name_en' => 'Updated',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name_es', 'Actualizado');
    }

    public function test_delete_sub_process_removes_it(): void
    {
        $superadmin = $this->createSuperadmin();
        $process = $this->makeProcess();
        $subProcess = SubProcess::factory()->create([
            'process_id' => $process->id,
            'code' => 'DE-P01',
        ]);

        $response = $this->actingAs($superadmin)->deleteJson('/api/v1/sub-processes/'.$subProcess->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('sub_processes', ['id' => $subProcess->id]);
    }

    public function test_has_bpmn_flag_reflects_diagram_presence(): void
    {
        $superadmin = $this->createSuperadmin();
        $process = $this->makeProcess();

        $withBpmn = SubProcess::factory()->withBpmn()->create([
            'process_id' => $process->id,
            'code' => 'DE-P01',
        ]);
        $withoutBpmn = SubProcess::factory()->create([
            'process_id' => $process->id,
            'code' => 'DE-P02',
        ]);

        // Touch via update endpoint to read the resource shape back.
        $a = $this->actingAs($superadmin)->putJson('/api/v1/sub-processes/'.$withBpmn->id, ['name_es' => 'A', 'name_en' => 'A']);
        $b = $this->actingAs($superadmin)->putJson('/api/v1/sub-processes/'.$withoutBpmn->id, ['name_es' => 'B', 'name_en' => 'B']);

        $a->assertJsonPath('data.has_bpmn', true);
        $b->assertJsonPath('data.has_bpmn', false);
    }

    public function test_sub_sub_processes_count_shown_in_map_tree(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $map = $this->createMapWithCategories($this->createCompany($franchise));
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_VALUE_CHAIN);
        $process = Process::factory()->create(['category_id' => $category->id, 'code' => 'AR']);
        $subProcess = SubProcess::factory()->create(['process_id' => $process->id, 'code' => 'AR-P02']);

        // Two sub-sub-processes → counter "2" next to the BPMN icon.
        $this->actingAs($superadmin)->postJson('/api/v1/sub-processes/'.$subProcess->id.'/sub-sub-processes', ['name_es' => 'S1', 'name_en' => 'S1']);
        $this->actingAs($superadmin)->postJson('/api/v1/sub-processes/'.$subProcess->id.'/sub-sub-processes', ['name_es' => 'S2', 'name_en' => 'S2']);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/process-maps/'.$map->id);

        $response->assertStatus(200);
        $tree = collect($response->json('data.categories'))
            ->firstWhere('type', ProcessCategory::TYPE_VALUE_CHAIN);
        $this->assertSame(2, $tree['processes'][0]['sub_processes'][0]['sub_sub_processes_count']);
    }

    public function test_admin_sm_cannot_create_sub_process_in_other_franchise(): void
    {
        $ownFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($ownFranchise);
        $process = $this->makeProcess($otherFranchise);

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/processes/'.$process->id.'/sub-processes',
            ['name_es' => 'Nope', 'name_en' => 'Nope']
        );

        $response->assertStatus(403);
    }
}

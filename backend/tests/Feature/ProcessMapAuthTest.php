<?php

namespace Tests\Feature;

use App\Models\ProcessCategory;
use App\Models\ProcessMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

/**
 * Covers authentication (401) and role-based authorization for the process
 * maps module: system_admin has read+write parity with superadmin,
 * system_admin_readonly is read-only, and non-admin roles are denied.
 */
class ProcessMapAuthTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Authentication (401)
    // -----------------------------------------------------------------------

    public function test_unauthenticated_cannot_list_maps(): void
    {
        $this->getJson('/api/v1/process-maps')->assertStatus(401);
    }

    public function test_unauthenticated_cannot_create_map(): void
    {
        $company = $this->createCompany();

        $this->postJson('/api/v1/process-maps', [
            'company_id' => $company->id,
            'type' => 'custom',
            'name_es' => 'X',
            'name_en' => 'X',
        ])->assertStatus(401);
    }

    public function test_unauthenticated_cannot_view_map(): void
    {
        $map = ProcessMap::factory()->create();

        $this->getJson('/api/v1/process-maps/'.$map->id)->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // system_admin — full read + write parity with superadmin
    // -----------------------------------------------------------------------

    public function test_system_admin_can_view_and_create_and_delete_maps(): void
    {
        $admin = $this->createSystemAdmin();
        $company = $this->createCompany();

        $created = $this->actingAs($admin)->postJson('/api/v1/process-maps', [
            'company_id' => $company->id,
            'type' => 'custom',
            'name_es' => 'Mapa',
            'name_en' => 'Map',
        ]);
        $created->assertStatus(201);
        $mapId = (int) $created->json('data.id');

        $this->actingAs($admin)->getJson('/api/v1/process-maps/'.$mapId)->assertStatus(200);
        $this->actingAs($admin)->deleteJson('/api/v1/process-maps/'.$mapId)->assertStatus(200);
    }

    // -----------------------------------------------------------------------
    // system_admin_readonly — read allowed, writes denied
    // -----------------------------------------------------------------------

    public function test_readonly_admin_can_list_and_view_maps(): void
    {
        $readonly = $this->createReadonlyAdmin();
        $map = $this->createMapWithCategories($this->createCompany());

        $this->actingAs($readonly)->getJson('/api/v1/process-maps')->assertStatus(200);
        $this->actingAs($readonly)->getJson('/api/v1/process-maps/'.$map->id)->assertStatus(200);
    }

    public function test_readonly_admin_cannot_create_map(): void
    {
        $readonly = $this->createReadonlyAdmin();
        $company = $this->createCompany();

        $this->actingAs($readonly)->postJson('/api/v1/process-maps', [
            'company_id' => $company->id,
            'type' => 'custom',
            'name_es' => 'X',
            'name_en' => 'X',
        ])->assertStatus(403);
    }

    public function test_readonly_admin_cannot_delete_map(): void
    {
        $readonly = $this->createReadonlyAdmin();
        $map = ProcessMap::factory()->create();

        $this->actingAs($readonly)->deleteJson('/api/v1/process-maps/'.$map->id)->assertStatus(403);
        $this->assertDatabaseHas('process_maps', ['id' => $map->id]);
    }

    public function test_readonly_admin_cannot_rename_division(): void
    {
        $readonly = $this->createReadonlyAdmin();
        $map = $this->createMapWithCategories($this->createCompany());
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_STRATEGIC);

        $this->actingAs($readonly)->patchJson('/api/v1/process-categories/'.$category->id, [
            'name_es' => 'X',
            'name_en' => 'X',
        ])->assertStatus(403);
    }

    public function test_readonly_admin_cannot_create_process(): void
    {
        $readonly = $this->createReadonlyAdmin();
        $map = $this->createMapWithCategories($this->createCompany());
        $category = $this->categoryOfType($map, ProcessCategory::TYPE_STRATEGIC);

        $this->actingAs($readonly)->postJson(
            '/api/v1/process-categories/'.$category->id.'/processes',
            ['code' => 'GTH', 'name_es' => 'X', 'name_en' => 'X']
        )->assertStatus(403);
    }

    // -----------------------------------------------------------------------
    // Non-admin roles — denied
    // -----------------------------------------------------------------------

    public function test_sb_owner_cannot_list_maps(): void
    {
        $sbOwner = $this->createSbOwner();

        $this->actingAs($sbOwner)->getJson('/api/v1/process-maps')->assertStatus(403);
    }
}

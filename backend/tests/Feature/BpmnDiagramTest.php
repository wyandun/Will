<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\SubSubProcess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

/**
 * Covers the BPMN diagram + documents feature: per-language XML persistence
 * (separate bpmn_xml_es / bpmn_xml_en, never a combined field), the detail
 * endpoint shape (breadcrumb + documents + manual_url), document CRUD by URL,
 * and the franchise/role authorization matrix for both process levels.
 */
class BpmnDiagramTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    private const XML = '<?xml version="1.0"?><definitions><process><startEvent id="s"/></process></definitions>';

    public function test_upload_bpmn_es_persists_only_spanish_column(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        $response = $this->actingAs($superadmin)->putJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/bpmn',
            ['lang' => 'es', 'bpmn_xml' => self::XML]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.bpmn_xml_es', self::XML)
            ->assertJsonPath('data.bpmn_xml_en', null)
            ->assertJsonPath('data.has_bpmn', true);

        $this->assertSame(self::XML, $subProcess->fresh()->bpmn_xml_es);
        $this->assertNull($subProcess->fresh()->bpmn_xml_en);
    }

    public function test_upload_bpmn_en_persists_only_english_column(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        $this->actingAs($superadmin)->putJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/bpmn',
            ['lang' => 'en', 'bpmn_xml' => self::XML]
        )->assertStatus(200);

        $this->assertNull($subProcess->fresh()->bpmn_xml_es);
        $this->assertSame(self::XML, $subProcess->fresh()->bpmn_xml_en);
    }

    public function test_upload_bpmn_rejects_invalid_lang_and_non_xml(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        $this->actingAs($superadmin)->putJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/bpmn',
            ['lang' => 'fr', 'bpmn_xml' => self::XML]
        )->assertStatus(422)->assertJsonValidationErrors(['lang']);

        $this->actingAs($superadmin)->putJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/bpmn',
            ['lang' => 'es', 'bpmn_xml' => 'not xml']
        )->assertStatus(422)->assertJsonValidationErrors(['bpmn_xml']);
    }

    public function test_show_returns_both_diagrams_breadcrumb_and_documents(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());
        $subProcess->update(['bpmn_xml_es' => self::XML, 'bpmn_xml_en' => self::XML]);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/sub-processes/'.$subProcess->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.bpmn_xml_es', self::XML)
            ->assertJsonPath('data.bpmn_xml_en', self::XML)
            ->assertJsonPath('data.breadcrumb.macro.code', 'OPS')
            ->assertJsonStructure([
                'data' => [
                    'id', 'code', 'bpmn_xml_es', 'bpmn_xml_en', 'manual_url',
                    'documents',
                    'breadcrumb' => ['map' => ['id', 'name_es', 'name_en'], 'macro' => ['id', 'code']],
                ],
            ]);
    }

    public function test_add_document_with_file_and_manual_shortcut(): void
    {
        Storage::fake('public');
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        // A metadata-only document (both files optional).
        $this->actingAs($superadmin)->post(
            '/api/v1/sub-processes/'.$subProcess->id.'/documents',
            ['type' => 'FOR', 'title_es' => 'Formato', 'title_en' => 'Form'],
            ['Accept' => 'application/json']
        )->assertStatus(201)->assertJsonPath('data.code', 'OPS-P01-FOR-01');

        // The manual (MP) with an uploaded PDF — becomes manual_url.
        $manual = $this->actingAs($superadmin)->post(
            '/api/v1/sub-processes/'.$subProcess->id.'/documents',
            [
                'type' => 'MP', 'title_es' => 'Manual', 'title_en' => 'Manual',
                'file_es' => UploadedFile::fake()->create('manual.pdf', 120, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        );
        $manual->assertStatus(201)
            ->assertJsonPath('data.code', 'OPS-P01-MP-01')
            ->assertJsonPath('data.file_name', 'manual.pdf');

        $show = $this->actingAs($superadmin)->getJson('/api/v1/sub-processes/'.$subProcess->id);
        $this->assertNotNull($show->json('data.manual_url'));
        $this->assertCount(2, $show->json('data.documents'));
        $this->assertSame($manual->json('data.id'), $subProcess->fresh()->manual_document_id);
    }

    public function test_document_rejects_disallowed_file_type(): void
    {
        Storage::fake('public');
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        $this->actingAs($superadmin)->post(
            '/api/v1/sub-processes/'.$subProcess->id.'/documents',
            [
                'type' => 'FOR', 'title_es' => 'F', 'title_en' => 'F',
                'file_es' => UploadedFile::fake()->create('evil.exe', 10),
            ],
            ['Accept' => 'application/json']
        )->assertStatus(422)->assertJsonValidationErrors(['file_es']);
    }

    public function test_document_stores_reviewer_valid_from_and_notes(): void
    {
        Storage::fake('public');
        $franchise = Franchise::factory()->create();
        $superadmin = $this->createSuperadmin();
        $reviewer = $this->userWithRole(Role::ADMIN_SM, ['sm_franchise_id' => $franchise->id]);
        $subProcess = $this->buildFullTree($this->createCompany($franchise));

        $res = $this->actingAs($superadmin)->post(
            '/api/v1/sub-processes/'.$subProcess->id.'/documents',
            [
                'type' => 'FOR', 'title_es' => 'F', 'title_en' => 'F',
                'reviewed_by' => $reviewer->id, 'valid_from' => '2026-06-01', 'notes' => 'Hello',
            ],
            ['Accept' => 'application/json']
        );

        $res->assertStatus(201)
            ->assertJsonPath('data.reviewed_by.id', $reviewer->id)
            ->assertJsonPath('data.valid_from', '2026-06-01')
            ->assertJsonPath('data.notes', 'Hello');
        $this->assertNotNull($res->json('data.reviewed_at'));
    }

    public function test_show_returns_franchise_reviewers(): void
    {
        $franchise = Franchise::factory()->create();
        $superadmin = $this->createSuperadmin();
        User::factory()->create(['sm_franchise_id' => $franchise->id, 'name' => 'Zoe']);
        User::factory()->create(['sm_franchise_id' => $franchise->id, 'name' => 'Ana']);
        $subProcess = $this->buildFullTree($this->createCompany($franchise));

        $res = $this->actingAs($superadmin)->getJson('/api/v1/sub-processes/'.$subProcess->id);

        $res->assertStatus(200);
        $names = collect($res->json('reviewers'))->pluck('name');
        $this->assertTrue($names->contains('Ana') && $names->contains('Zoe'));
    }

    public function test_update_and_delete_document(): void
    {
        Storage::fake('public');
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        $doc = $this->actingAs($superadmin)->post(
            '/api/v1/sub-processes/'.$subProcess->id.'/documents',
            ['type' => 'FOR', 'title_es' => 'F', 'title_en' => 'F'],
            ['Accept' => 'application/json']
        )->json('data.id');

        $this->actingAs($superadmin)->post(
            '/api/v1/process-documents/'.$doc,
            ['title_es' => 'Actualizado'],
            ['Accept' => 'application/json']
        )->assertStatus(200)->assertJsonPath('data.title_es', 'Actualizado');

        $this->actingAs($superadmin)->deleteJson('/api/v1/process-documents/'.$doc)->assertStatus(200);
        $this->assertSoftDeleted('process_documents', ['id' => $doc]);
    }

    public function test_admin_sm_of_other_franchise_cannot_upload_or_add_document(): void
    {
        $own = Franchise::factory()->create();
        $other = Franchise::factory()->create();
        $admin = $this->createAdminSm($own);
        $subProcess = $this->buildFullTree($this->createCompany($other));

        $this->actingAs($admin)->putJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/bpmn',
            ['lang' => 'es', 'bpmn_xml' => self::XML]
        )->assertStatus(403);

        $this->actingAs($admin)->postJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/documents',
            ['type' => 'FOR', 'title_es' => 'F', 'title_en' => 'F', 'file_url' => 'https://example.com/f.pdf']
        )->assertStatus(403);
    }

    public function test_readonly_admin_can_view_but_not_upload(): void
    {
        $readonly = $this->createReadonlyAdmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        $this->actingAs($readonly)->getJson('/api/v1/sub-processes/'.$subProcess->id)->assertStatus(200);

        $this->actingAs($readonly)->putJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/bpmn',
            ['lang' => 'es', 'bpmn_xml' => self::XML]
        )->assertStatus(403);
    }

    public function test_admin_sm_of_own_franchise_can_upload(): void
    {
        $own = Franchise::factory()->create();
        $admin = $this->createAdminSm($own);
        $subProcess = $this->buildFullTree($this->createCompany($own));

        $this->actingAs($admin)->putJson(
            '/api/v1/sub-processes/'.$subProcess->id.'/bpmn',
            ['lang' => 'es', 'bpmn_xml' => self::XML]
        )->assertStatus(200);
    }

    public function test_sub_sub_process_upload_and_show(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());
        $subSub = SubSubProcess::factory()->create([
            'sub_process_id' => $subProcess->id,
            'code' => 'OPS-P01-S01',
        ]);

        $this->actingAs($superadmin)->putJson(
            '/api/v1/sub-sub-processes/'.$subSub->id.'/bpmn',
            ['lang' => 'en', 'bpmn_xml' => self::XML]
        )->assertStatus(200)->assertJsonPath('data.bpmn_xml_en', self::XML);

        $this->actingAs($superadmin)->getJson('/api/v1/sub-sub-processes/'.$subSub->id)
            ->assertStatus(200)
            ->assertJsonPath('data.breadcrumb.process.code', 'OPS-P01')
            ->assertJsonPath('data.breadcrumb.macro.code', 'OPS');

        $this->assertNull($subSub->fresh()->bpmn_xml_es);
        $this->assertSame(self::XML, $subSub->fresh()->bpmn_xml_en);
    }
}

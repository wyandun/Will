<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Franchise;
use App\Models\SubProcess;
use App\Models\SubSubProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

class NodeLinkTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    // ── helpers ─────────────────────────────────────────────────────────────

    private function makeDocument(SubProcess|SubSubProcess $owner, array $overrides = []): Document
    {
        $type = $owner instanceof SubProcess ? 'sub_process' : 'sub_sub_process';

        return Document::create(array_merge([
            'documentable_type' => $type,
            'documentable_id' => $owner->id,
            'code' => 'DOC-'.uniqid(),
            'type' => 'PRC',
            'title_es' => 'Documento',
            'title_en' => 'Document',
            'version' => 1,
            'is_current' => true,
        ], $overrides));
    }

    private function urlPayload(string $nodeId, string $url): array
    {
        return ['node_links' => [$nodeId => ['type' => 'url', 'value' => $url]]];
    }

    // ── authorization ───────────────────────────────────────────────────────

    public function test_superadmin_saves_node_links_on_sub_process(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        $response = $this->actingAs($superadmin)->putJson(
            "/api/v1/sub-processes/{$subProcess->id}/node-links",
            ['node_links' => ['Task_1' => ['type' => 'url', 'value' => 'https://example.com']]]
        );

        $response->assertOk()
            ->assertJsonPath('data.node_links.Task_1.type', 'url')
            ->assertJsonPath('data.node_links.Task_1.value', 'https://example.com');

        $fresh = $subProcess->fresh();
        $this->assertNotNull($fresh?->node_links);
        $this->assertSame('url', $fresh->node_links['Task_1']['type']);
    }

    public function test_admin_sm_can_save_links_on_own_franchise_map(): void
    {
        $franchise = Franchise::factory()->create();
        $adminSm = $this->createAdminSm($franchise);
        $subProcess = $this->buildFullTree($this->createCompany($franchise));

        $this->actingAs($adminSm)->putJson(
            "/api/v1/sub-processes/{$subProcess->id}/node-links",
            $this->urlPayload('Task_A', 'https://docs.example.com')
        )->assertOk();
    }

    public function test_admin_sm_cannot_save_links_on_other_franchise(): void
    {
        $otherFranchise = Franchise::factory()->create();
        $myFranchise = Franchise::factory()->create();
        $adminSm = $this->createAdminSm($myFranchise);
        $subProcess = $this->buildFullTree($this->createCompany($otherFranchise));

        $this->actingAs($adminSm)->putJson(
            "/api/v1/sub-processes/{$subProcess->id}/node-links",
            $this->urlPayload('Task_A', 'https://docs.example.com')
        )->assertForbidden();
    }

    public function test_readonly_admin_cannot_save_links(): void
    {
        $readonly = $this->createReadonlyAdmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        $this->actingAs($readonly)->putJson(
            "/api/v1/sub-processes/{$subProcess->id}/node-links",
            $this->urlPayload('Task_A', 'https://docs.example.com')
        )->assertForbidden();
    }

    // ── validation ──────────────────────────────────────────────────────────

    public function test_rejects_invalid_type_and_missing_value(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        $this->actingAs($superadmin)->putJson(
            "/api/v1/sub-processes/{$subProcess->id}/node-links",
            ['node_links' => ['Task_1' => ['type' => 'invalid_type', 'value' => 'x']]]
        )->assertUnprocessable();
    }

    public function test_rejects_javascript_scheme_url(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        $this->actingAs($superadmin)->putJson(
            "/api/v1/sub-processes/{$subProcess->id}/node-links",
            ['node_links' => ['Task_1' => ['type' => 'url', 'value' => 'javascript:alert(1)']]]
        )->assertUnprocessable();
    }

    public function test_rejects_document_from_another_sub_process(): void
    {
        $superadmin = $this->createSuperadmin();
        $company = $this->createCompany();
        $ownSubProcess = $this->buildFullTree($company);
        $otherSubProcess = $this->buildFullTree($company);

        $foreignDoc = $this->makeDocument($otherSubProcess);

        $this->actingAs($superadmin)->putJson(
            "/api/v1/sub-processes/{$ownSubProcess->id}/node-links",
            ['node_links' => ['Task_1' => ['type' => 'document', 'value' => $foreignDoc->id]]]
        )->assertUnprocessable();
    }

    public function test_rejects_subprocess_from_another_map(): void
    {
        $superadmin = $this->createSuperadmin();
        $company = $this->createCompany();
        $subProcess = $this->buildFullTree($company);
        $subProcessOtherMap = $this->buildFullTree($company); // different map tree

        $this->actingAs($superadmin)->putJson(
            "/api/v1/sub-processes/{$subProcess->id}/node-links",
            ['node_links' => ['Gw_1' => ['type' => 'subprocess', 'value' => $subProcessOtherMap->id]]]
        )->assertUnprocessable();
    }

    // ── cross-level (SubSubProcess) ──────────────────────────────────────────

    public function test_sub_sub_process_links_use_own_documents_and_map_sub_processes(): void
    {
        $superadmin = $this->createSuperadmin();
        $company = $this->createCompany();
        $subProcess = $this->buildFullTree($company);
        $subSubProcess = SubSubProcess::factory()->create(['sub_process_id' => $subProcess->id]);

        $ownDoc = $this->makeDocument($subSubProcess);
        $foreignDoc = $this->makeDocument($subProcess); // belongs to parent, not to subsub

        // own document → accepted
        $this->actingAs($superadmin)->putJson(
            "/api/v1/sub-sub-processes/{$subSubProcess->id}/node-links",
            ['node_links' => ['Task_1' => ['type' => 'document', 'value' => $ownDoc->id]]]
        )->assertOk();

        // parent's document → rejected
        $this->actingAs($superadmin)->putJson(
            "/api/v1/sub-sub-processes/{$subSubProcess->id}/node-links",
            ['node_links' => ['Task_1' => ['type' => 'document', 'value' => $foreignDoc->id]]]
        )->assertUnprocessable();

        // subprocess from same map → accepted (subSubProcess links to a SubProcess of the same map)
        $this->actingAs($superadmin)->putJson(
            "/api/v1/sub-sub-processes/{$subSubProcess->id}/node-links",
            ['node_links' => ['Gw_1' => ['type' => 'subprocess', 'value' => $subProcess->id]]]
        )->assertOk();
    }

    // ── payload edge cases ───────────────────────────────────────────────────

    public function test_empty_payload_clears_links(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());
        $subProcess->update(['node_links' => ['Task_1' => ['type' => 'url', 'value' => 'https://x.com']]]);

        $this->actingAs($superadmin)->putJson(
            "/api/v1/sub-processes/{$subProcess->id}/node-links",
            ['node_links' => []]
        )->assertOk();

        $this->assertNull($subProcess->fresh()?->node_links);
    }

    public function test_show_includes_node_links(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());
        $subProcess->update(['node_links' => ['Task_1' => ['type' => 'url', 'value' => 'https://example.com']]]);

        $this->actingAs($superadmin)->getJson(
            "/api/v1/sub-processes/{$subProcess->id}"
        )->assertOk()
            ->assertJsonPath('data.node_links.Task_1.type', 'url');
    }

    public function test_generic_update_cannot_set_node_links(): void
    {
        $superadmin = $this->createSuperadmin();
        $subProcess = $this->buildFullTree($this->createCompany());

        $this->actingAs($superadmin)->putJson(
            "/api/v1/sub-processes/{$subProcess->id}",
            ['name_es' => 'Test', 'node_links' => ['Task_1' => ['type' => 'url', 'value' => 'https://x.com']]]
        )->assertOk();

        $this->assertNull($subProcess->fresh()?->node_links);
    }
}

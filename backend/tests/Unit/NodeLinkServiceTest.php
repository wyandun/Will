<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\SubProcess;
use App\Models\SubSubProcess;
use App\Services\NodeLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

class NodeLinkServiceTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    private NodeLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NodeLinkService;
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function buildSubProcess(): SubProcess
    {
        return $this->buildFullTree($this->createCompany());
    }

    private function makeDocument(SubProcess|SubSubProcess $owner): Document
    {
        $type = $owner instanceof SubProcess ? 'sub_process' : 'sub_sub_process';

        return Document::create([
            'documentable_type' => $type,
            'documentable_id' => $owner->id,
            'code' => 'DOC-'.uniqid(),
            'type' => 'PRC',
            'title_es' => 'Doc',
            'title_en' => 'Doc',
            'version' => 1,
            'is_current' => true,
        ]);
    }

    // ── persistence ──────────────────────────────────────────────────────────

    public function test_persists_url_link_and_returns_model(): void
    {
        $subProcess = $this->buildSubProcess();

        $result = $this->service->update($subProcess, [
            'Task_1' => ['type' => 'url', 'value' => 'https://example.com'],
        ]);

        $this->assertInstanceOf(SubProcess::class, $result);
        $this->assertSame('url', $result->fresh()?->node_links['Task_1']['type']);
    }

    public function test_normalizes_document_value_to_int(): void
    {
        $subProcess = $this->buildSubProcess();
        $doc = $this->makeDocument($subProcess);

        $result = $this->service->update($subProcess, [
            'Task_1' => ['type' => 'document', 'value' => (string) $doc->id],
        ]);

        $links = $result->fresh()?->node_links;
        $this->assertIsInt($links['Task_1']['value']);
        $this->assertSame($doc->id, $links['Task_1']['value']);
    }

    public function test_empty_links_sets_column_null(): void
    {
        $subProcess = $this->buildSubProcess();
        $subProcess->update(['node_links' => ['Task_1' => ['type' => 'url', 'value' => 'https://x.com']]]);

        $this->service->update($subProcess, []);

        $this->assertNull($subProcess->fresh()?->node_links);
    }

    // ── cross-tenant validation ───────────────────────────────────────────────

    public function test_throws_for_document_belonging_to_different_sub_process(): void
    {
        $subProcess = $this->buildSubProcess();
        $other = $this->buildSubProcess();
        $foreignDoc = $this->makeDocument($other);

        $this->expectException(ValidationException::class);

        $this->service->update($subProcess, [
            'Task_1' => ['type' => 'document', 'value' => $foreignDoc->id],
        ]);
    }

    public function test_throws_for_subprocess_from_different_map(): void
    {
        $subProcess = $this->buildSubProcess();
        $otherSubProcess = $this->buildSubProcess(); // different map

        $this->expectException(ValidationException::class);

        $this->service->update($subProcess, [
            'Gw_1' => ['type' => 'subprocess', 'value' => $otherSubProcess->id],
        ]);
    }

    public function test_resolves_map_through_subsub_relation_chain(): void
    {
        $subProcess = $this->buildSubProcess();
        $subSubProcess = SubSubProcess::factory()->create(['sub_process_id' => $subProcess->id]);

        // subProcess is from the same map → should be accepted
        $result = $this->service->update($subSubProcess, [
            'Gw_1' => ['type' => 'subprocess', 'value' => $subProcess->id],
        ]);

        $this->assertNotNull($result->fresh()?->node_links);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SubSubProcess;
use App\Services\BpmnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

class BpmnServiceTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    private BpmnService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BpmnService;
    }

    public function test_store_es_writes_only_spanish_column(): void
    {
        $subProcess = $this->buildFullTree($this->createCompany());

        $updated = $this->service->store($subProcess, 'es', '<xml-es/>');

        $this->assertSame('<xml-es/>', $updated->bpmn_xml_es);
        $this->assertNull($updated->bpmn_xml_en);
    }

    public function test_store_en_writes_only_english_column(): void
    {
        $subProcess = $this->buildFullTree($this->createCompany());

        $updated = $this->service->store($subProcess, 'en', '<xml-en/>');

        $this->assertNull($updated->bpmn_xml_es);
        $this->assertSame('<xml-en/>', $updated->bpmn_xml_en);
    }

    public function test_store_keeps_both_languages_independent(): void
    {
        $subProcess = $this->buildFullTree($this->createCompany());

        $this->service->store($subProcess, 'es', '<es/>');
        $updated = $this->service->store($subProcess, 'en', '<en/>');

        $this->assertSame('<es/>', $updated->bpmn_xml_es);
        $this->assertSame('<en/>', $updated->bpmn_xml_en);
    }

    public function test_store_works_for_sub_sub_process(): void
    {
        $subProcess = $this->buildFullTree($this->createCompany());
        $subSub = SubSubProcess::factory()->create([
            'sub_process_id' => $subProcess->id,
            'code' => 'OPS-P01-S01',
        ]);

        $updated = $this->service->store($subSub, 'es', '<sub/>');

        $this->assertSame('<sub/>', $updated->bpmn_xml_es);
        $this->assertNull($updated->bpmn_xml_en);
    }
}

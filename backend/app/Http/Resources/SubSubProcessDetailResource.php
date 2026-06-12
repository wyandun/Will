<?php

namespace App\Http\Resources;

use App\Models\Document;
use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\ProcessMap;
use App\Models\SubProcess;
use App\Models\SubSubProcess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail view of a SubSubProcess: full BPMN XML (es/en), documents, manual URL
 * and the breadcrumb (map → macroprocess → process).
 *
 * @mixin SubSubProcess
 */
class SubSubProcessDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Narrowing with instanceof so PHPStan resolves the concrete types at
        // each step; matches the pattern used in SubSubProcessPolicy::resolveMap.
        $subProcess = $this->subProcess instanceof SubProcess ? $this->subProcess : null;
        $process = $subProcess?->process instanceof Process ? $subProcess->process : null;
        $category = $process?->category instanceof ProcessCategory ? $process->category : null;
        $map = $category?->processMap instanceof ProcessMap ? $category->processMap : null;

        return [
            'id' => $this->id,
            'sub_process_id' => $this->sub_process_id,
            'code' => $this->code,
            'name_es' => $this->name_es,
            'name_en' => $this->name_en,
            'description' => $this->description,
            'order_index' => $this->order_index,
            'has_bpmn' => ! empty($this->bpmn_xml_es) || ! empty($this->bpmn_xml_en),
            'bpmn_xml_es' => $this->bpmn_xml_es,
            'bpmn_xml_en' => $this->bpmn_xml_en,
            'walkthrough_es' => $this->walkthrough_es,
            'walkthrough_en' => $this->walkthrough_en,
            'node_links' => $this->node_links,
            'manual_document_id' => $this->manual_document_id,
            'manual_url' => $this->resolveManualUrl(),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'breadcrumb' => [
                'map' => $map ? [
                    'id' => $map->id,
                    'name_es' => $map->name_es,
                    'name_en' => $map->name_en,
                ] : null,
                'macro' => $process ? [
                    'id' => $process->id,
                    'code' => $process->code,
                    'name_es' => $process->name_es,
                    'name_en' => $process->name_en,
                ] : null,
                'process' => $subProcess ? [
                    'id' => $subProcess->id,
                    'code' => $subProcess->code,
                    'name_es' => $subProcess->name_es,
                    'name_en' => $subProcess->name_en,
                ] : null,
            ],
        ];
    }

    private function resolveManualUrl(): ?string
    {
        $manual = $this->documents->firstWhere('id', $this->manual_document_id)
            ?? $this->documents->firstWhere('type', 'MP');

        if (! $manual instanceof Document) {
            return null;
        }

        return $manual->file_url ?? $manual->file_url_en;
    }
}

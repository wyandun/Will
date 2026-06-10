<?php

namespace App\Http\Resources;

use App\Models\SubProcess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SubProcess */
class SubProcessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'process_id' => $this->process_id,
            'code' => $this->code,
            'name_es' => $this->name_es,
            'name_en' => $this->name_en,
            'description' => $this->description,
            'order_index' => $this->order_index,
            'has_bpmn' => ! empty($this->bpmn_xml_es) || ! empty($this->bpmn_xml_en),
            'sub_sub_processes_count' => $this->when(
                $this->relationLoaded('subSubProcesses'),
                fn () => $this->subSubProcesses->count(),
                0
            ),
            'sub_sub_processes' => SubSubProcessResource::collection($this->whenLoaded('subSubProcesses')),
        ];
    }
}

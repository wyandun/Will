<?php

namespace App\Http\Resources;

use App\Models\SubSubProcess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SubSubProcess */
class SubSubProcessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sub_process_id' => $this->sub_process_id,
            'code' => $this->code,
            'name_es' => $this->name_es,
            'name_en' => $this->name_en,
            'description' => $this->description,
            'order_index' => $this->order_index,
            'has_bpmn' => ! empty($this->bpmn_xml_es) || ! empty($this->bpmn_xml_en),
        ];
    }
}

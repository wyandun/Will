<?php

namespace App\Http\Resources;

use App\Models\Process;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Process */
class ProcessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'code' => $this->code,
            'name_es' => $this->name_es,
            'name_en' => $this->name_en,
            'description' => $this->description,
            'order_index' => $this->order_index,
            'sub_processes_count' => $this->when(
                $this->relationLoaded('subProcesses'),
                fn () => $this->subProcesses->count(),
                0
            ),
            'sub_processes' => SubProcessResource::collection($this->whenLoaded('subProcesses')),
        ];
    }
}

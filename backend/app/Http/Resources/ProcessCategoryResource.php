<?php

namespace App\Http\Resources;

use App\Models\ProcessCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProcessCategory */
class ProcessCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'process_map_id' => $this->process_map_id,
            'type' => $this->type,
            'name_es' => $this->name_es,
            'name_en' => $this->name_en,
            'order_index' => $this->order_index,
            'processes' => ProcessResource::collection($this->whenLoaded('processes')),
        ];
    }
}

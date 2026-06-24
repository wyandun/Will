<?php

namespace App\Http\Resources;

use App\Models\ProcessCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProcessCategory */
class ProcessTreeCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'name_es' => $this->name_es,
            'name_en' => $this->name_en,
            'processes' => ProcessTreeProcessResource::collection($this->processes),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\ProcessMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProcessMap */
class ProcessMapTreeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'type' => $this->type,
            'name_es' => $this->name_es,
            'name_en' => $this->name_en,
            'description' => $this->description,
            'brand_color' => $this->brand_color,
            'logo_url' => $this->logo_url,
            'node_styles' => $this->node_styles,
            'is_active' => (bool) $this->is_active,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'sm_franchise_id' => $this->company->sm_franchise_id,
                'franchise' => $this->company->relationLoaded('franchise') && $this->company->franchise
                    ? [
                        'id' => $this->company->franchise->id,
                        'name' => $this->company->franchise->name,
                    ]
                    : null,
            ]),
            'categories' => ProcessCategoryResource::collection($this->whenLoaded('categories')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

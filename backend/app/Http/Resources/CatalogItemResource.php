<?php

namespace App\Http\Resources;

use App\Models\CatalogItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CatalogItem */
class CatalogItemResource extends JsonResource
{
    /**
     * Transform the catalog item into an API-ready array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level->value,
            'parent_id' => $this->parent_id,
            'name' => app()->getLocale() === 'es' ? $this->name_es : $this->name_en,
            'name_es' => $this->name_es,
            'name_en' => $this->name_en,
            'description_es' => $this->description_es,
            'description_en' => $this->description_en,
            'is_monthly' => $this->is_monthly,
            'order_index' => $this->order_index,
            'estimated_hours' => $this->estimated_hours,
            'service_type' => $this->service_type?->value,
            'parent' => $this->whenLoaded('parent', fn () => new self($this->parent)),
            'children' => $this->whenLoaded('children', fn () => self::collection($this->children)),
            // Only expose total_hours when children are loaded; otherwise computing
            // it would trigger N+1 queries when listing many items.
            'total_hours' => $this->whenLoaded('children', fn () => $this->total_hours),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'franchise_id' => $this->franchise_id,
            'catalog_item_id' => $this->catalog_item_id,
            'type' => $this->type,
            'start_date' => $this->start_date?->toDateString(),
            'notes' => $this->notes,
            'status' => $this->status,
            'company_name' => $this->whenLoaded('company', fn () => $this->company->name),
            'franchise_name' => $this->whenLoaded('franchise', fn () => $this->franchise->name),
            'catalog_item_name' => $this->whenLoaded('catalogItem', fn () => $this->catalogItem->name_es),
            'deliverables' => ProjectDeliverableResource::collection($this->whenLoaded('deliverables')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

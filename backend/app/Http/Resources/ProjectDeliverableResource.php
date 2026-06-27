<?php

namespace App\Http\Resources;

use App\Models\ProjectDeliverable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectDeliverable */
class ProjectDeliverableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'catalog_item_id' => $this->catalog_item_id,
            'name' => $this->name,
            'phase' => $this->phase,
            'estimated_start_date' => $this->estimated_start_date?->toDateString(),
            'estimated_end_date' => $this->estimated_end_date?->toDateString(),
            'status' => $this->status,
            'order' => $this->order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

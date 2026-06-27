<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
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
        // Computed KPI fields — only calculated when deliverables are loaded.
        $deliverables = $this->relationLoaded('deliverables') ? $this->deliverables : null;

        $deliverablesTotal = $deliverables instanceof Collection ? $deliverables->count() : 0;

        $deliverablesCompleted = $deliverables instanceof Collection
            ? $deliverables->filter(fn ($d) => $d->status->value === 'completed')->count()
            : 0;

        $progressPercentage = $deliverablesTotal > 0
            ? (int) round(($deliverablesCompleted / $deliverablesTotal) * 100)
            : 0;

        $estimatedEndDate = $deliverables instanceof Collection
            ? $deliverables->max(fn ($d) => $d->estimated_end_date?->toDateString())
            : null;

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'franchise_id' => $this->franchise_id,
            'catalog_item_id' => $this->catalog_item_id,
            'type' => $this->type,
            'start_date' => $this->start_date?->toDateString(),
            'notes' => $this->notes,
            'status' => $this->status,
            // Nested objects for the detail view.
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ]),
            'franchise' => $this->whenLoaded('franchise', fn () => [
                'id' => $this->franchise->id,
                'name' => $this->franchise->name,
            ]),
            // Flat convenience fields kept for backward compatibility (listing page uses them).
            'company_name' => $this->whenLoaded('company', fn () => $this->company->name),
            'franchise_name' => $this->whenLoaded('franchise', fn () => $this->franchise->name),
            'catalog_item_name' => $this->whenLoaded('catalogItem', fn () => $this->catalogItem->name_es),
            'deliverables' => ProjectDeliverableResource::collection($this->whenLoaded('deliverables')),
            // KPI fields for the detail / Gantt view.
            'progress_percentage' => $this->whenLoaded('deliverables', $progressPercentage),
            'estimated_end_date' => $this->whenLoaded('deliverables', $estimatedEndDate),
            'deliverables_completed' => $this->whenLoaded('deliverables', $deliverablesCompleted),
            'deliverables_total' => $this->whenLoaded('deliverables', $deliverablesTotal),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Repository */
class RepositoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ]),
            'franchise' => $this->whenLoaded('company', function () {
                if (
                    $this->company->relationLoaded('franchise')
                    && $this->company->franchise !== null
                ) {
                    return [
                        'id' => $this->company->franchise->id,
                        'name' => $this->company->franchise->name,
                    ];
                }

                return null;
            }),
            'sub_franchise' => $this->whenLoaded('subFranchise', fn () => $this->subFranchise
                ? ['id' => $this->subFranchise->id, 'name' => $this->subFranchise->name]
                : null),
            'documents_count' => $this->documents_count ?? 0,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Franchise;
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
            // franchise is derived from the company (company.franchise), whereas
            // subFranchise is a direct relation on the repository. The loading is
            // intentionally asymmetric because it mirrors the data model: a
            // repository has no own franchise, it inherits it from its company.
            // When company.franchise is not eager-loaded the key is omitted
            // (MissingValue) instead of emitting a misleading "franchise": null.
            'franchise' => $this->when(
                $this->relationLoaded('company') && $this->company?->relationLoaded('franchise'),
                fn () => $this->franchiseShape($this->company->franchise)
            ),
            'sub_franchise' => $this->whenLoaded('subFranchise',
                fn () => $this->franchiseShape($this->subFranchise)
            ),
            'documents_count' => $this->whenCounted('documents'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function franchiseShape(?Franchise $model): ?array
    {
        return $model ? ['id' => $model->id, 'name' => $model->name] : null;
    }
}

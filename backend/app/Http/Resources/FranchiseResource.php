<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FranchiseResource extends JsonResource
{
    /**
     * Transform the franchise model into an API-ready array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'parent_company_id' => $this->parent_company_id,
            'owner_user_id' => $this->owner_user_id,
            'region' => $this->region,
            'address' => $this->address,
            'phone' => $this->phone,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

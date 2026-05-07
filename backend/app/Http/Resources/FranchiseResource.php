<?php

namespace App\Http\Resources;

use App\Models\Franchise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Franchise */
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
            'email' => $this->email,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'address' => $this->address,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            // withCount appends virtual attributes; use array_key_exists
            // instead of whenHas (which is for actual model attributes).
            'admins_count' => $this->when(
                array_key_exists('admins_count', $this->resource->getAttributes()),
                fn () => $this->admins_count,
            ),
            'clients_count' => $this->when(
                array_key_exists('clients_count', $this->resource->getAttributes()),
                fn () => $this->clients_count,
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

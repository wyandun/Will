<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Company */
class CompanyResource extends JsonResource
{
    /**
     * Transform the company model into an API-ready array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'industry' => $this->industry,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'state' => $this->state,
            'country' => $this->country,
            'logo_path' => $this->logo_path,
            'employees_count' => $this->employees_count,
            'annual_revenue' => $this->annual_revenue,
            'years_operating' => $this->years_operating,
            'sm_franchise_id' => $this->sm_franchise_id,
            // Franchise name is included when the relationship is loaded
            'franchise_name' => $this->whenLoaded('franchise', fn () => $this->franchise->name),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class FranchiseMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'area' => $this->area,
            'role' => $this->whenLoaded('roles', fn () => $this->getRoleNames()->first()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

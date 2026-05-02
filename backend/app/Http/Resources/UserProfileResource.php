<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
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
            'bio' => $this->bio,
            'birth_date' => $this->birth_date?->toDateString(),
            'avatar_url' => $this->avatar_url,
            'role' => $this->getRoleNames()->first(),
        ];
    }
}

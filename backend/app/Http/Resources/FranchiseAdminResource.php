<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class FranchiseAdminResource extends JsonResource
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
            'area' => $this->area,
            'avatar_url' => $this->avatar_url,
            'role' => $this->getRoleNames()->first(),
            'sm_franchise_id' => $this->sm_franchise_id,
            'deleted_at' => $this->deleted_at,
            'permissions' => $this->whenLoaded('userPermissions', fn () => $this->userPermissions->map(fn ($p) => [
                'module' => $p->module,
                'can_read' => $p->can_read,
                'can_write' => $p->can_write,
            ])),
        ];
    }
}

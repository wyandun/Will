<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin User */
class FranchiseMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'area' => $this->area,
            'role' => $this->whenLoaded('roles', function () {
                /** @var Role|null $firstRole */
                $firstRole = $this->roles->first();

                return $firstRole?->name;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

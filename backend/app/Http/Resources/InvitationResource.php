<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->roles->pluck('name'),
            'invited_by' => $this->whenLoaded('invitedBy', fn () => [
                'id' => $this->invitedBy?->id,
                'name' => $this->invitedBy?->name,
            ]),
            'invitation_expires_at' => $this->invitation_expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

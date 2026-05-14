<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class InvitationResource extends JsonResource
{
    /**
     * Explicit allowlist — prevents invitation_token, sm_franchise_id, inviter_id,
     * and other sensitive fields from leaking through the invitation endpoints.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->getRoleNames()->first(),
            'invited_by' => $this->whenLoaded('invitedBy', fn () => [
                'id' => $this->invitedBy->id,
                'name' => $this->invitedBy->name,
            ]),
            'invitation_expires_at' => $this->invitation_expires_at?->toIso8601String(),
            'email_sent' => ! is_null($this->email_sent_at),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

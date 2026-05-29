<?php

namespace App\Http\Requests\Invitation;

use App\Http\Requests\AuthenticatedRequest;

class ResendInvitationRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

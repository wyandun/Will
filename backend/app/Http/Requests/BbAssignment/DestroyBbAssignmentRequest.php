<?php

namespace App\Http\Requests\BbAssignment;

use App\Http\Requests\AuthenticatedRequest;

class DestroyBbAssignmentRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

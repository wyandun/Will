<?php

namespace App\Http\Requests\SystemAdmin;

use App\Http\Requests\AuthenticatedRequest;

class DestroySystemAdminRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

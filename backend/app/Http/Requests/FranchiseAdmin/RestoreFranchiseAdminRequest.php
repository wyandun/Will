<?php

namespace App\Http\Requests\FranchiseAdmin;

use App\Http\Requests\AuthenticatedRequest;

class RestoreFranchiseAdminRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

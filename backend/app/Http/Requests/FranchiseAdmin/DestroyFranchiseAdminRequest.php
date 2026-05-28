<?php

namespace App\Http\Requests\FranchiseAdmin;

use App\Http\Requests\AuthenticatedRequest;

class DestroyFranchiseAdminRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

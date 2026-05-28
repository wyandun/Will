<?php

namespace App\Http\Requests\Franchise;

use App\Http\Requests\AuthenticatedRequest;

class DestroyFranchiseRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

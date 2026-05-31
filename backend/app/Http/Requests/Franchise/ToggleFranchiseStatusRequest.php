<?php

namespace App\Http\Requests\Franchise;

use App\Http\Requests\AuthenticatedRequest;

class ToggleFranchiseStatusRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

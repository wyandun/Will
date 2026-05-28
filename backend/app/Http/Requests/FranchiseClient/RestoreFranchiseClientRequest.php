<?php

namespace App\Http\Requests\FranchiseClient;

use App\Http\Requests\AuthenticatedRequest;

class RestoreFranchiseClientRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\AuthenticatedRequest;

class DestroyCompanyRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

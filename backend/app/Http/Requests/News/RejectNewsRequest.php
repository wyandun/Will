<?php

namespace App\Http\Requests\News;

use App\Http\Requests\AuthenticatedRequest;

class RejectNewsRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

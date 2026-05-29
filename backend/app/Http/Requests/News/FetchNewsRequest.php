<?php

namespace App\Http\Requests\News;

use App\Http\Requests\AuthenticatedRequest;

class FetchNewsRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

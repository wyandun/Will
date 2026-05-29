<?php

namespace App\Http\Requests\News;

use App\Http\Requests\AuthenticatedRequest;

class PublishNewsRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

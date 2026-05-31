<?php

namespace App\Http\Requests\Feed;

use App\Http\Requests\AuthenticatedRequest;

class DestroyPostRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

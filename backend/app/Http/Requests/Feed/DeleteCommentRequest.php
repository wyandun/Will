<?php

namespace App\Http\Requests\Feed;

use App\Http\Requests\AuthenticatedRequest;

class DeleteCommentRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

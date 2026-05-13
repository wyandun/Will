<?php

namespace App\Http\Requests\Feed;

use App\Http\Requests\AuthenticatedRequest;

class StoreCommentRequest extends AuthenticatedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}

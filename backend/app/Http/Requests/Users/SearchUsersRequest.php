<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\AuthenticatedRequest;

class SearchUsersRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}

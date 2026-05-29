<?php

namespace App\Http\Requests\Event;

use App\Http\Requests\AuthenticatedRequest;

class DestroyEventRequest extends AuthenticatedRequest
{
    public function rules(): array
    {
        return [];
    }
}

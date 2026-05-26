<?php

namespace App\Http\Requests\Event;

use App\Http\Requests\AuthenticatedRequest;
use Illuminate\Validation\Rule;

class ListEventsRequest extends AuthenticatedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'start_from' => ['sometimes', 'date'],
            'end_before' => ['sometimes', 'date', Rule::when($this->has('start_from'), 'after:start_from')],
            'per_page' => ['sometimes', 'integer', 'min:5', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}

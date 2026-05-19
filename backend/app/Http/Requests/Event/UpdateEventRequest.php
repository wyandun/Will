<?php

namespace App\Http\Requests\Event;

use App\Enums\EventColor;
use App\Http\Requests\AuthenticatedRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends AuthenticatedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => array_filter([
                'sometimes',
                'date',
                $this->has('start_at') ? 'after_or_equal:start_at' : null,
            ]),
            'all_day' => ['sometimes', 'boolean'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'color' => ['sometimes', 'string', Rule::in(EventColor::values())],
            'visibility' => ['sometimes', 'string', Rule::in(['private', 'franchise', 'public'])],
            'type' => ['sometimes', 'string', Rule::in(['casual', 'meeting', 'deadline', 'reminder', 'training'])],
        ];
    }
}

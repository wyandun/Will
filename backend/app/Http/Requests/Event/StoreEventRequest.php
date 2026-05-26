<?php

namespace App\Http\Requests\Event;

use App\Enums\EventColor;
use App\Http\Requests\AuthenticatedRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends AuthenticatedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'all_day' => ['sometimes', 'boolean'],
            'timezone' => ['required', 'string', 'timezone'],
            'color' => ['sometimes', 'string', Rule::in(EventColor::values())],
            'visibility' => ['sometimes', 'string', Rule::in(['private', 'franchise', 'public'])],
            'type' => ['sometimes', 'string', Rule::in(['casual', 'meeting', 'deadline', 'reminder', 'training'])],
            'rrule' => ['nullable', 'string', 'max:500'],
            'reminder_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}

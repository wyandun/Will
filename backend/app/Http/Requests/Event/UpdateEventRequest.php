<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['sometimes', 'required', 'date', 'after_or_equal:start_at'],
            'timezone' => ['sometimes', 'timezone'],
            'all_day' => ['boolean'],
            'color' => ['nullable', 'string', 'max:10'],
            'visibility' => ['nullable', 'string', 'in:private,franchise,public'],
            'type' => ['nullable', 'string', 'in:casual,meeting,deadline,reminder,training'],
        ];
    }
}

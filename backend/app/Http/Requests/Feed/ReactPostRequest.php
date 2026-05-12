<?php

namespace App\Http\Requests\Feed;

use App\Enums\ReactionEmoji;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', Rule::enum(ReactionEmoji::class)],
        ];
    }
}

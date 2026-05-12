<?php

namespace App\Http\Requests\Feed;

use App\Enums\ReactionEmoji;
use App\Http\Requests\AuthenticatedRequest;
use Illuminate\Validation\Rule;

class ReactPostRequest extends AuthenticatedRequest
{
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

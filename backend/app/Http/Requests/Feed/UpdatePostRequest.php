<?php

namespace App\Http\Requests\Feed;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    /**
     * Authorization is handled in FeedController — always pass here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string', 'in:announcement,news,training,alert'],
            'visibility' => ['sometimes', 'string', 'in:global,franchise'],
            'is_pinned' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ];
    }
}

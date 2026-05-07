<?php

namespace App\Http\Requests\Feed;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['required', 'string', 'in:announcement,news,training,alert'],
            'visibility' => ['required', 'string', 'in:global,franchise'],
            'is_pinned' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ];
    }
}

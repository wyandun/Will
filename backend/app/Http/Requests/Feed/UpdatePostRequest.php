<?php

namespace App\Http\Requests\Feed;

use App\Http\Requests\AuthenticatedRequest;

class UpdatePostRequest extends AuthenticatedRequest
{
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
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,csv', 'max:20480'],
        ];
    }
}

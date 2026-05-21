<?php

namespace App\Http\Requests\Feed;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::ADMIN_SM]);
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
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,csv', 'max:20480'],
        ];
    }
}

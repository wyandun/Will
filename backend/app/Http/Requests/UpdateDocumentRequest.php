<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');
        $owner = $document?->documentable;

        return $owner !== null && (bool) $this->user()?->can('update', $owner);
    }

    /**
     * @return array<string, array<int, mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(StoreDocumentRequest::TYPES)],
            'title_es' => ['sometimes', 'string', 'max:200'],
            'title_en' => ['sometimes', 'string', 'max:200'],
            'file_es' => ['nullable', 'file', 'mimes:'.StoreDocumentRequest::FILE_MIMES, 'max:20480'],
            'file_en' => ['nullable', 'file', 'mimes:'.StoreDocumentRequest::FILE_MIMES, 'max:20480'],
            'reviewed_by' => ['nullable', 'integer', 'exists:users,id'],
            'approved_by' => ['nullable', 'integer', 'exists:users,id'],
            'valid_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_manual' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Repository;

use App\Enums\DocumentSection;
use App\Enums\SetupCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepositoryDocumentRequest extends FormRequest
{
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
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'section' => ['required', Rule::enum(DocumentSection::class)],
            'setup_category' => [
                'required_if:section,setup',
                'prohibited_unless:section,setup',
                'nullable',
                Rule::enum(SetupCategory::class),
            ],
            'process_code' => ['nullable', 'string', 'max:40'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif', 'max:20480'],
        ];
    }
}

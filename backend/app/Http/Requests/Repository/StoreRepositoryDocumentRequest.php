<?php

namespace App\Http\Requests\Repository;

use Illuminate\Foundation\Http\FormRequest;

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
            'section' => ['required', 'string', 'in:setup,process,record'],
            'setup_category' => ['required_if:section,setup', 'nullable', 'string', 'in:legal,hr,certificates,marketing,sops'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif', 'max:20480'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    /** Allowed document types (matches the document-type picker). */
    public const TYPES = ['MP', 'CR', 'MN', 'AN', 'PO', 'PR', 'IN', 'FOR', 'REG'];

    /** Allowed uploaded file extensions: PDFs, Word, PowerPoint, Excel. */
    public const FILE_MIMES = 'pdf,doc,docx,ppt,pptx,xls,xlsx';

    public function authorize(): bool
    {
        $model = $this->route('subProcess') ?? $this->route('subSubProcess');

        return $model !== null && (bool) $this->user()?->can('update', $model);
    }

    /**
     * @return array<string, array<int, mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(self::TYPES)],
            'title_es' => ['required', 'string', 'max:200'],
            'title_en' => ['required', 'string', 'max:200'],
            'file_es' => ['nullable', 'file', 'mimes:'.self::FILE_MIMES, 'max:20480'],
            'file_en' => ['nullable', 'file', 'mimes:'.self::FILE_MIMES, 'max:20480'],
            'reviewed_by' => ['nullable', 'integer', 'exists:users,id'],
            'approved_by' => ['nullable', 'integer', 'exists:users,id'],
            'valid_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_manual' => ['sometimes', 'boolean'],
        ];
    }
}

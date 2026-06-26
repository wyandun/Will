<?php

namespace App\Http\Requests\Repository;

use App\Enums\DocumentSection;
use App\Enums\SetupCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRepositoryDocumentRequest extends FormRequest
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
            // Nullable so the controller can apply the SETUP default; when present
            // it must be a valid DocumentSection. Authorization (403) still runs
            // first in the controller, before any section/category is read.
            'section' => ['nullable', Rule::enum(DocumentSection::class)],
            'category' => ['nullable', Rule::enum(SetupCategory::class)],
            'process_code' => ['nullable', 'string', 'max:40'],
        ];
    }
}

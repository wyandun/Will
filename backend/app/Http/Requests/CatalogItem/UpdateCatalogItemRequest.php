<?php

namespace App\Http\Requests\CatalogItem;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogItemRequest extends FormRequest
{
    /**
     * Authorization is handled by CatalogItemPolicy — always pass here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'level' => ['sometimes', 'in:bundle,service,deliverable'],
            'name_es' => ['sometimes', 'required', 'string', 'max:255'],
            'name_en' => ['sometimes', 'required', 'string', 'max:255'],
            'description_es' => ['sometimes', 'nullable', 'string'],
            'description_en' => ['sometimes', 'nullable', 'string'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:catalog_items,id'],
            'is_monthly' => ['sometimes', 'boolean'],
            'order_index' => ['sometimes', 'integer', 'min:0'],
            'estimated_hours' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999.99'],
            'service_type' => ['sometimes', 'nullable', 'in:individual,package,retainer'],
            'deliverable_ids' => ['sometimes', 'nullable', 'array'],
            'deliverable_ids.*' => ['integer', 'exists:catalog_items,id'],
            'service_ids' => ['sometimes', 'nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:catalog_items,id'],
        ];
    }
}

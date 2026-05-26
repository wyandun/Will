<?php

namespace App\Http\Requests\CatalogItem;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogItemRequest extends FormRequest
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
            'level' => ['required', 'in:bundle,service,deliverable'],
            'name_es' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_es' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:catalog_items,id'],
            'is_monthly' => ['boolean'],
            'order_index' => ['integer', 'min:0'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'service_type' => ['nullable', 'in:individual,package,retainer'],
            'deliverable_ids' => ['nullable', 'array'],
            'deliverable_ids.*' => ['integer', 'exists:catalog_items,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:catalog_items,id'],
        ];
    }
}

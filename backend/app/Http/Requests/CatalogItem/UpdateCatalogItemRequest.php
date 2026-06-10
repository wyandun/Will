<?php

namespace App\Http\Requests\CatalogItem;

use Illuminate\Validation\Validator;

class UpdateCatalogItemRequest extends CatalogItemRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = $this->sharedRules();

        // On update every field is optional; name_* must stay non-empty when present.
        array_unshift($rules['level'], 'sometimes');
        array_unshift($rules['name_es'], 'sometimes', 'required');
        array_unshift($rules['name_en'], 'sometimes', 'required');
        array_unshift($rules['description_es'], 'sometimes');
        array_unshift($rules['description_en'], 'sometimes');
        array_unshift($rules['parent_id'], 'sometimes');
        array_unshift($rules['is_monthly'], 'sometimes');
        array_unshift($rules['order_index'], 'sometimes');
        array_unshift($rules['estimated_hours'], 'sometimes');
        array_unshift($rules['service_type'], 'sometimes');
        array_unshift($rules['deliverable_ids'], 'sometimes');
        array_unshift($rules['service_ids'], 'sometimes');

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // Only enforce parent × level coherence when at least one of the two
            // fields is present in the payload.
            $this->validateParentLevelCoherence($v, strict: false);
        });
    }
}

<?php

namespace App\Http\Requests\CatalogItem;

use Illuminate\Validation\Validator;

class StoreCatalogItemRequest extends CatalogItemRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = $this->sharedRules();

        // Required-on-create fields.
        array_unshift($rules['level'], 'required');
        array_unshift($rules['name_es'], 'required');
        array_unshift($rules['name_en'], 'required');

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->validateParentLevelCoherence($v, strict: true);
        });
    }
}

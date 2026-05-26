<?php

namespace App\Http\Requests\CatalogItem;

use App\Enums\CatalogLevel;
use App\Enums\CatalogServiceType;
use App\Models\CatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Base form request for catalog item create / update.
 *
 * Concrete requests should call {@see sharedRules()} and decorate each rule
 * list with `required` or `sometimes` as needed.
 */
abstract class CatalogItemRequest extends FormRequest
{
    /**
     * Authorization is handled by CatalogItemPolicy — always pass here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Shared field rules (without required / sometimes prefixes).
     *
     * @return array<string, array<int, mixed>>
     */
    protected function sharedRules(): array
    {
        return [
            'level' => [Rule::enum(CatalogLevel::class)],
            'name_es' => ['string', 'max:255'],
            'name_en' => ['string', 'max:255'],
            'description_es' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:catalog_items,id'],
            'is_monthly' => ['boolean'],
            'order_index' => ['integer', 'min:0'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'service_type' => ['nullable', Rule::enum(CatalogServiceType::class)],
            'deliverable_ids' => ['nullable', 'array'],
            'deliverable_ids.*' => [
                'integer',
                Rule::exists('catalog_items', 'id')->where('level', CatalogLevel::Deliverable->value),
            ],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => [
                'integer',
                Rule::exists('catalog_items', 'id')->where('level', CatalogLevel::Service->value),
            ],
        ];
    }

    /**
     * Cross-field validation: parent_id × level coherence.
     *
     * - A bundle cannot have a parent.
     * - A deliverable must declare a parent service.
     *
     * Concrete requests opt-in via the $strict flag so Update can skip
     * the rule when the relevant fields are not present in the payload.
     */
    protected function validateParentLevelCoherence(Validator $validator, bool $strict): void
    {
        $hasLevel = $this->has('level');
        $hasParent = $this->has('parent_id');

        if (! $strict && ! $hasLevel && ! $hasParent) {
            return;
        }

        // On update ($strict=false), if level is absent resolve it from the
        // existing model so that PATCH {parent_id: null} on a deliverable is
        // still caught even when level is omitted from the payload.
        if (! $hasLevel && ! $strict) {
            $existing = $this->route('catalogItem');
            $levelEnum = $existing instanceof CatalogItem ? $existing->level : null;
        } else {
            $levelEnum = CatalogLevel::tryFrom((string) $this->input('level'));
        }

        $parentId = $this->input('parent_id');

        if ($levelEnum === CatalogLevel::Bundle && $parentId !== null) {
            $validator->errors()->add('parent_id', 'Bundles cannot have a parent.');
        }

        if ($levelEnum === CatalogLevel::Deliverable && $parentId === null) {
            $validator->errors()->add('parent_id', 'Deliverables must specify a parent service.');
        }
    }
}

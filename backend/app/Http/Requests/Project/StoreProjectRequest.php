<?php

namespace App\Http\Requests\Project;

use App\Enums\CatalogLevel;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
{
    /**
     * Authorization is handled by ProjectPolicy — always pass here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'franchise_id' => ['required', 'integer', 'exists:franchises,id'],
            'catalog_item_id' => ['required', 'integer', 'exists:catalog_items,id'],
            'type' => ['required', 'string', Rule::enum(CatalogLevel::class)],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Enforce franchise scope: admin_sm can only create projects within
     * their own franchise, and the chosen company must belong to that franchise.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if (! $user->hasRole(Role::ADMIN_SM)) {
                return;
            }

            if (! $user->sm_franchise_id) {
                $validator->errors()->add('franchise_id', 'projects.no_franchise_assigned');

                return;
            }

            if ((int) $this->input('franchise_id') !== (int) $user->sm_franchise_id) {
                $validator->errors()->add('franchise_id', 'projects.franchise_scope_violation');
            }
        });
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'projects.company_required',
            'company_id.exists' => 'projects.company_not_found',
            'franchise_id.required' => 'projects.franchise_required',
            'franchise_id.exists' => 'projects.franchise_not_found',
            'catalog_item_id.required' => 'projects.catalog_item_required',
            'catalog_item_id.exists' => 'projects.catalog_item_not_found',
            'type.required' => 'projects.type_required',
            'start_date.required' => 'projects.start_date_required',
            'start_date.date' => 'projects.start_date_invalid',
        ];
    }
}

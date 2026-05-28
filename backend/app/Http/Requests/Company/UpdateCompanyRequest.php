<?php

namespace App\Http\Requests\Company;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Authorization is handled by CompanyPolicy — always pass here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'sm_franchise_id' => ['sometimes', 'integer', 'exists:franchises,id'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:50'],
            'country' => ['sometimes', 'nullable', 'string', 'max:50'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Enforce franchise scope: admin_sm cannot move a company to a different franchise.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            // Guard: admin_sm must have a franchise assigned before scoping can work.
            if ($user->hasRole(Role::ADMIN_SM) && ! $user->sm_franchise_id) {
                $validator->errors()->add('sm_franchise_id', 'companies.form.no_franchise');

                return;
            }

            if (
                $user->hasRole(Role::ADMIN_SM)
                && $this->has('sm_franchise_id')
                && (int) $this->input('sm_franchise_id') !== (int) $user->sm_franchise_id
            ) {
                $validator->errors()->add('sm_franchise_id', 'companies.form.franchise_scope_update');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.max'               => 'companies.form.name_max',
            'sm_franchise_id.exists' => 'companies.form.sm_franchise_invalid',
            'phone.max'              => 'companies.form.phone_max',
            'state.max'              => 'companies.form.state_max',
            'country.max'            => 'companies.form.country_max',
            'email.email'            => 'companies.form.email_invalid',
            'website.url'            => 'companies.form.website_url',
        ];
    }
}

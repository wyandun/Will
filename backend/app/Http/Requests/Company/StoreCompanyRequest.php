<?php

namespace App\Http\Requests\Company;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCompanyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'sm_franchise_id' => ['required', 'integer', 'exists:franchises,id'],
            'industry' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:50'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Enforce franchise scope: admin_sm can only create companies within
     * their own franchise.
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
                && (int) $this->input('sm_franchise_id') !== (int) $user->sm_franchise_id
            ) {
                $validator->errors()->add('sm_franchise_id', 'companies.form.franchise_scope_create');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'companies.form.name_required',
            'name.max' => 'companies.form.name_max',
            'sm_franchise_id.required' => 'companies.form.sm_franchise_required',
            'sm_franchise_id.exists' => 'companies.form.sm_franchise_invalid',
            'phone.max' => 'companies.form.phone_max',
            'state.max' => 'companies.form.state_max',
            'country.max' => 'companies.form.country_max',
            'email.email' => 'companies.form.email_invalid',
            'website.url' => 'companies.form.website_url',
        ];
    }
}

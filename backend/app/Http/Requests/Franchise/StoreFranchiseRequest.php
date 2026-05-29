<?php

namespace App\Http\Requests\Franchise;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFranchiseRequest extends FormRequest
{
    /**
     * Authorization is handled by FranchisePolicy — always pass here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Default type to 'sm' when not provided — all franchises created through
     * the portal admin form are SM-type unless explicitly specified otherwise.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('type')) {
            $this->merge(['type' => 'sm']);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('franchises')->whereNull('deleted_at')],
            'type' => ['sometimes', 'string', 'in:sm,sub'],
            'email' => ['nullable', 'email', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'region' => ['nullable', 'string', 'max:255'],
            'parent_company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'franchises.form.name_required',
            'name.unique' => 'franchises.form.name_unique',
            'country.required' => 'franchises.form.country_required',
            'timezone.required' => 'franchises.form.timezone_required',
            'timezone.timezone' => 'franchises.form.timezone_invalid',
            'phone.required' => 'franchises.form.phone_required',
            'address.required' => 'franchises.form.address_required',
        ];
    }
}

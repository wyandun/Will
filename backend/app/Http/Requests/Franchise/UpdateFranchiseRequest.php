<?php

namespace App\Http\Requests\Franchise;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFranchiseRequest extends FormRequest
{
    /**
     * Authorization is handled by FranchisePolicy — always pass here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $franchiseId = $this->route('franchise')->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('franchises')->whereNull('deleted_at')->ignore($franchiseId)],
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
            'name.required' => 'Franchise name is required.',
            'name.unique' => 'A franchise with this name already exists.',
            'country.required' => 'Country is required.',
            'timezone.required' => 'Time zone is required.',
            'timezone.timezone' => 'franchises.form.timezone_invalid',
            'phone.required' => 'Phone number is required.',
            'address.required' => 'Address is required.',
        ];
    }
}

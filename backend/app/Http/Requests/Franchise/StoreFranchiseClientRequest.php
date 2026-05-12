<?php

namespace App\Http\Requests\Franchise;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreFranchiseClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'client_type' => ['required', 'string', 'in:owner,investor'],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'franchise_detail.form.full_name_required',
            'email.required' => 'franchise_detail.form.email_required',
            'email.email' => 'franchise_detail.form.email_invalid',
            'email.unique' => 'franchise_detail.form.email_taken',
            'password.required' => 'franchise_detail.form.password_required',
            'client_type.required' => 'franchise_detail.form.client_type_required',
            'client_type.in' => 'franchise_detail.form.client_type_invalid',
        ];
    }
}

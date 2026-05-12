<?php

namespace App\Http\Requests\Franchise;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreFranchiseAdminRequest extends FormRequest
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
            'area' => ['required', 'string', 'in:full_access,accounting,marketing,operations,legal,human_resources'],
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
            'area.required' => 'franchise_detail.form.area_required',
            'area.in' => 'franchise_detail.form.area_invalid',
        ];
    }
}

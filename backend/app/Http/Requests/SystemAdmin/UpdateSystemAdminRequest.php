<?php

namespace App\Http\Requests\SystemAdmin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UpdateSystemAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->route('system_admin')),
            ],
            'password' => ['nullable', 'string', Password::min(12)],
            'role' => ['required', 'string', 'in:' . Role::SYSTEM_ADMIN . ',' . Role::SYSTEM_ADMIN_READONLY],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'system_admins.form.name_required',
            'email.required' => 'system_admins.form.email_required',
            'email.email' => 'system_admins.form.email_invalid',
            'email.unique' => 'system_admins.form.email_unique',
            'password.min' => 'system_admins.form.password_min',
            'role.in' => 'system_admins.form.role_invalid',
        ];
    }
}

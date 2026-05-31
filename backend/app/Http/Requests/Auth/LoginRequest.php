<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * All login attempts are allowed — authentication is enforced in AuthService.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'auth.errors.email_required',
            'email.email' => 'auth.errors.email_invalid',
            'password.required' => 'auth.errors.password_required',
            'password.min' => 'auth.errors.password_min',
        ];
    }
}

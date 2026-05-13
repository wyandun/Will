<?php

namespace App\Http\Requests\Franchise;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

abstract class StoreFranchiseMemberRequest extends FormRequest
{
    /**
     * Authorization is explicitly delegated to the Controller.
     * We return true here to prevent bypassing if this request
     * is ever instantiated programmatically without the controller gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->uncompromised()],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function baseMessages(): array
    {
        return [
            'name.required' => 'franchise_detail.form.full_name_required',
            'email.required' => 'franchise_detail.form.email_required',
            'email.email' => 'franchise_detail.form.email_invalid',
            'email.unique' => 'franchise_detail.form.email_taken',
            'password.required' => 'franchise_detail.form.password_required',
        ];
    }
}

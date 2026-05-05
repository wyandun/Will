<?php

namespace App\Http\Requests\Franchise;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'name' => ['sometimes', 'string', 'min:1', 'max:255'],
            'type' => ['sometimes', 'string', 'in:sm,sub'],
            'parent_company_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
            'owner_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'El nombre no puede estar vacío.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'type.in' => 'El tipo debe ser "sm" o "sub".',
            'parent_company_id.exists' => 'La empresa padre no existe.',
            'owner_user_id.exists' => 'El usuario propietario no existe.',
            'phone.max' => 'El teléfono no puede superar los 30 caracteres.',
            'email.email' => 'El correo electrónico debe ser válido.',
        ];
    }
}

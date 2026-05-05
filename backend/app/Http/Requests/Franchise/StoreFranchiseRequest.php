<?php

namespace App\Http\Requests\Franchise;

use Illuminate\Foundation\Http\FormRequest;

class StoreFranchiseRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'type' => ['required', 'string', 'in:sm,sub'],
            'parent_company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'region' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la franquicia es obligatorio.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'type.required' => 'El tipo de franquicia es obligatorio.',
            'type.in' => 'El tipo debe ser "sm" o "sub".',
            'parent_company_id.exists' => 'La empresa padre no existe.',
            'owner_user_id.exists' => 'El usuario propietario no existe.',
            'phone.max' => 'El teléfono no puede superar los 30 caracteres.',
        ];
    }
}

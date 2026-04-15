<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
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
            'name'            => ['sometimes', 'string', 'max:255'],
            'sm_franchise_id' => ['sometimes', 'integer', 'exists:franchises,id'],
            'industry'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'address'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone'           => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'           => ['sometimes', 'nullable', 'email', 'max:255'],
            'website'         => ['sometimes', 'nullable', 'url', 'max:255'],
            'city'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'state'           => ['sometimes', 'nullable', 'string', 'max:50'],
            'country'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'logo_path'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes'           => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max'               => 'El nombre no puede superar los 255 caracteres.',
            'sm_franchise_id.exists' => 'La franquicia SM seleccionada no existe.',
            'phone.max'              => 'El teléfono no puede superar los 30 caracteres.',
            'state.max'              => 'El estado no puede superar los 50 caracteres.',
            'country.max'            => 'El país no puede superar los 50 caracteres.',
            'email.email'            => 'El correo electrónico no tiene un formato válido.',
            'website.url'            => 'El sitio web no tiene un formato de URL válido.',
        ];
    }
}

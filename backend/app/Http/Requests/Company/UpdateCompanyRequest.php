<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /**
     * Enforce franchise scope: admin_sm cannot move a company to a different franchise.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            // Guard: admin_sm must have a franchise assigned before scoping can work.
            if ($user->hasRole('admin_sm') && ! $user->sm_franchise_id) {
                $validator->errors()->add(
                    'sm_franchise_id',
                    'Tu cuenta no tiene una franquicia asignada. Contacta al superadmin.'
                );

                return;
            }

            if (
                $user->hasRole('admin_sm')
                && $this->has('sm_franchise_id')
                && (int) $this->input('sm_franchise_id') !== (int) $user->sm_franchise_id
            ) {
                $validator->errors()->add(
                    'sm_franchise_id',
                    'Solo puedes asignar empresas dentro de tu franquicia.'
                );
            }
        });
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

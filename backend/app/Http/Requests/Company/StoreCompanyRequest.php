<?php

namespace App\Http\Requests\Company;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCompanyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'sm_franchise_id' => ['required', 'integer', 'exists:franchises,id'],
            'industry' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:50'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Enforce franchise scope: admin_sm can only create companies within
     * their own franchise.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            // Guard: admin_sm must have a franchise assigned before scoping can work.
            if ($user->hasRole(Role::ADMIN_SM) && ! $user->sm_franchise_id) {
                $validator->errors()->add(
                    'sm_franchise_id',
                    'Tu cuenta no tiene una franquicia asignada. Contacta al superadmin.'
                );

                return;
            }

            if (
                $user->hasRole(Role::ADMIN_SM)
                && (int) $this->input('sm_franchise_id') !== (int) $user->sm_franchise_id
            ) {
                $validator->errors()->add(
                    'sm_franchise_id',
                    'Solo puedes crear empresas dentro de tu franquicia.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la empresa es obligatorio.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'sm_franchise_id.required' => 'La franquicia SM es obligatoria.',
            'sm_franchise_id.exists' => 'La franquicia SM seleccionada no existe.',
            'phone.max' => 'El teléfono no puede superar los 30 caracteres.',
            'state.max' => 'El estado no puede superar los 50 caracteres.',
            'country.max' => 'El país no puede superar los 50 caracteres.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'website.url' => 'El sitio web no tiene un formato de URL válido.',
        ];
    }
}

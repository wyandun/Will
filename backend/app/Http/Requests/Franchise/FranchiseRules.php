<?php

namespace App\Http\Requests\Franchise;

/**
 * Shared validation rules and messages for franchise store/update requests.
 *
 * Prevents divergence between the two request classes when new fields or
 * stricter constraints are added. Each consuming class calls sharedRules()
 * / sharedMessages() and merges with its own presence rules.
 */
trait FranchiseRules
{
    /**
     * Common field validation rules.
     *
     * @param  string  $presence  'required' for store, 'sometimes' for update
     */
    protected function sharedRules(string $presence = 'required'): array
    {
        $nullable = $presence === 'sometimes'
            ? [$presence, 'nullable']
            : ['nullable'];

        return [
            'name' => [$presence, 'string', 'min:1', 'max:255'],
            'type' => [$presence, 'string', 'in:sm,sub'],
            'parent_company_id' => array_merge($nullable, ['integer', 'exists:companies,id']),
            'owner_user_id' => array_merge($nullable, ['integer', 'exists:users,id']),
            'region' => array_merge($nullable, ['string', 'max:255']),
            'address' => array_merge($nullable, ['string', 'max:255']),
            'phone' => array_merge($nullable, ['string', 'max:30']),
            // Email is a franchise contact address, NOT used for authentication
            // or notification deduplication — duplicates across franchises are
            // acceptable by design.
            'email' => array_merge($nullable, ['email', 'max:255']),
            'country' => array_merge($nullable, ['string', 'max:255']),
            'timezone' => array_merge($nullable, ['string', 'timezone']),
        ];
    }

    /**
     * Common custom validation messages.
     */
    protected function sharedMessages(): array
    {
        return [
            'name.min' => 'El nombre no puede estar vacío.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'type.in' => 'El tipo debe ser "sm" o "sub".',
            'parent_company_id.exists' => 'La empresa padre no existe.',
            'owner_user_id.exists' => 'El usuario propietario no existe.',
            'phone.max' => 'El teléfono no puede superar los 30 caracteres.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'timezone.timezone' => 'La zona horaria no es válida.',
        ];
    }
}

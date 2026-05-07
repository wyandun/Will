<?php

namespace App\Http\Requests\Franchise;

use Illuminate\Foundation\Http\FormRequest;

class StoreFranchiseRequest extends FormRequest
{
    use FranchiseRules;

    /**
     * Authorization is handled by FranchisePolicy — always pass here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->sharedRules('required');
    }

    public function messages(): array
    {
        return array_merge($this->sharedMessages(), [
            'name.required' => 'El nombre de la franquicia es obligatorio.',
            'type.required' => 'El tipo de franquicia es obligatorio.',
        ]);
    }
}

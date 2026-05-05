<?php

namespace App\Http\Requests\Franchise;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFranchiseRequest extends FormRequest
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
        return $this->sharedRules('sometimes');
    }

    public function messages(): array
    {
        return $this->sharedMessages();
    }
}

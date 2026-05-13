<?php

namespace App\Http\Requests\Franchise;

use App\Enums\ClientType;

class StoreFranchiseClientRequest extends StoreFranchiseMemberRequest
{
    public function rules(): array
    {
        return array_merge($this->baseRules(), [
            'client_type' => ['required', 'string', ClientType::validationRule()],
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->baseMessages(), [
            'client_type.required' => 'franchise_detail.form.client_type_required',
            'client_type.in' => 'franchise_detail.form.client_type_invalid',
        ]);
    }
}

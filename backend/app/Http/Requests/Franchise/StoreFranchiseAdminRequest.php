<?php

namespace App\Http\Requests\Franchise;

use App\Enums\Area;

class StoreFranchiseAdminRequest extends StoreFranchiseMemberRequest
{
    public function rules(): array
    {
        return array_merge($this->baseRules(), [
            'area' => ['required', 'string', Area::validationRule()],
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->baseMessages(), [
            'area.required' => 'franchise_detail.form.area_required',
            'area.in' => 'franchise_detail.form.area_invalid',
        ]);
    }
}

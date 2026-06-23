<?php

namespace App\Http\Requests\Repository;

use App\Enums\FranchiseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepositoryRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller via RepositoryPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'integer',
                'exists:companies,id',
                Rule::unique('repositories', 'company_id')->where(function ($query) {
                    if ($this->input('sub_franchise_id') === null) {
                        return $query->whereNull('sub_franchise_id');
                    }

                    return $query->where('sub_franchise_id', $this->input('sub_franchise_id'));
                }),
            ],
            'sub_franchise_id' => [
                'nullable',
                'integer',
                Rule::exists('franchises', 'id')
                    ->where('type', FranchiseType::SUB->value)
                    ->where('company_id', $this->input('company_id')),
            ],
        ];
    }
}

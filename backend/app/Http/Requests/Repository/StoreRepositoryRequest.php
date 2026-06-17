<?php

namespace App\Http\Requests\Repository;

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
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'sub_franchise_id' => [
                'nullable',
                'integer',
                Rule::exists('franchises', 'id')->where('type', 'sub'),
            ],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcessMapRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller via the ProcessMapPolicy,
     * which needs the resolved Company (loaded from company_id) to apply the
     * admin_sm franchise-scope check. Returning true here defers the gate
     * decision until after validation has populated company_id.
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
            'type' => ['required', 'string', 'max:50'],
            'name_es' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}

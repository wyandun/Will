<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller via the ContractPolicy,
     * which resolves the client's company to apply the admin_sm franchise scope.
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
            'client_user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'draft_url' => ['nullable', 'url', 'max:2048'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}

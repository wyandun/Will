<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendContractRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller via the ContractPolicy.
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
            'template_id' => ['required'],
            'signers' => ['required', 'array', 'min:3'],
            'signers.*.name' => ['required', 'string', 'max:255'],
            'signers.*.email' => ['required', 'email', 'max:255'],
            'signers.*.role' => ['nullable', 'string', 'max:255'],
        ];
    }
}

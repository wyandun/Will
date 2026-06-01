<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubSubProcessRequest extends FormRequest
{
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
            'name_es' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadBpmnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('subProcess') ?? $this->route('subSubProcess');

        return $model !== null && (bool) $this->user()?->can('update', $model);
    }

    /**
     * @return array<string, array<int, mixed>|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'lang' => ['required', Rule::in(['es', 'en'])],
            'bpmn_xml' => [
                'required',
                'string',
                'max:5000000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! preg_match('#^\s*<#', $value)) {
                        $fail('The :attribute must be valid BPMN XML.');
                    }
                },
            ],
        ];
    }
}

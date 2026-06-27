<?php

namespace App\Http\Requests\AssessmentContact;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminNoteRequest extends FormRequest
{
    /**
     * Authorization is handled by AssessmentContactPolicy — always pass here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'admin_note.max' => 'assessments.admin_note_max',
        ];
    }
}

<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectDeliverableStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliverableStatusRequest extends FormRequest
{
    /**
     * Authorization is handled by ProjectPolicy — always pass here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ProjectDeliverableStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'projects.deliverable_status_required',
            'status.enum' => 'projects.deliverable_status_invalid',
        ];
    }
}

<?php

namespace App\Http\Requests\BbAssignment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBbAssignmentRequest extends FormRequest
{
    /**
     * Authorization is handled in BbAssignmentController — always pass here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bb_user_id' => [
                'required',
                'integer',
                // User must exist in the users table and have the 'bb' role.
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->whereExists(function ($sub) {
                        $sub->select('model_id')
                            ->from('model_has_roles')
                            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                            ->whereColumn('model_has_roles.model_id', 'users.id')
                            ->where('model_has_roles.model_type', \App\Models\User::class)
                            ->where('roles.name', 'bb');
                    });
                }),
            ],
            'company_id' => [
                'required',
                'integer',
                'exists:companies,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'bb_user_id.required' => 'El usuario BB es obligatorio.',
            'bb_user_id.exists'   => 'El usuario seleccionado no existe o no tiene el rol BB.',
            'company_id.required' => 'La empresa es obligatoria.',
            'company_id.exists'   => 'La empresa seleccionada no existe.',
        ];
    }
}

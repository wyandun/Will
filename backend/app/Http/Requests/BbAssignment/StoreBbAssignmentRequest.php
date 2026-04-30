<?php

namespace App\Http\Requests\BbAssignment;

use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
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
                            ->where('model_has_roles.model_type', User::class)
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

    /**
     * Enforce franchise scope: admin_sm cannot assign a BB to a company
     * that belongs to a different SM franchise.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $user = $this->user();

            if (! $user->hasRole('admin_sm')) {
                return;
            }

            $company = Company::find($this->input('company_id'));

            if ($company && (int) $user->sm_franchise_id !== (int) $company->sm_franchise_id) {
                $validator->errors()->add(
                    'company_id',
                    'Solo puedes asignar un BB a empresas dentro de tu franquicia.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'bb_user_id.required' => 'El usuario BB es obligatorio.',
            'bb_user_id.exists' => 'El usuario seleccionado no existe o no tiene el rol BB.',
            'company_id.required' => 'La empresa es obligatoria.',
            'company_id.exists' => 'La empresa seleccionada no existe.',
        ];
    }
}

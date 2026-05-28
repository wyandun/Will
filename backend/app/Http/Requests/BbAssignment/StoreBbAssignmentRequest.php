<?php

namespace App\Http\Requests\BbAssignment;

use App\Enums\Role;
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
                // User must exist in the users table and have the 'bb_employee' role.
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->whereExists(function ($sub) {
                        $sub->select('model_id')
                            ->from('model_has_roles')
                            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                            ->whereColumn('model_has_roles.model_id', 'users.id')
                            ->where('model_has_roles.model_type', User::class)
                            ->where('roles.name', Role::BB_EMPLOYEE);
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

            if (! $user->hasRole(Role::ADMIN_SM)) {
                return;
            }

            $company = Company::find($this->input('company_id'));

            if ($company && (int) $user->sm_franchise_id !== (int) $company->sm_franchise_id) {
                $validator->errors()->add('company_id', 'bb_assignments.form.franchise_scope');
            }
        });
    }

    public function messages(): array
    {
        return [
            'bb_user_id.required' => 'bb_assignments.form.bb_user_required',
            'bb_user_id.exists' => 'bb_assignments.form.bb_user_invalid',
            'company_id.required' => 'bb_assignments.form.company_required',
            'company_id.exists' => 'bb_assignments.form.company_invalid',
        ];
    }
}

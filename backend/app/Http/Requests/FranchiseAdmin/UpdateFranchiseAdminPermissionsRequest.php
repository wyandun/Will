<?php

namespace App\Http\Requests\FranchiseAdmin;

use App\Models\UserPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFranchiseAdminPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*.module' => ['required', 'string', Rule::in(UserPermission::ALL_MODULES)],
            'permissions.*.can_read' => ['required', 'boolean'],
            'permissions.*.can_write' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('permissions', []) as $i => $perm) {
                if (! empty($perm['can_write']) && empty($perm['can_read'])) {
                    $validator->errors()->add(
                        "permissions.{$i}.can_read",
                        'can_read must be true when can_write is true.'
                    );
                }
            }
        });
    }
}

<?php

namespace App\Http\Requests\Franchise;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubFranchiseRequest extends FormRequest
{
    /**
     * Gate here (before validation) so unauthorized roles receive 403,
     * not a 422 from a failing validation constraint (e.g. parent_company_id null).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SB_OWNER]);
    }

    /**
     * For sb_owner: force parent_company_id and owner_user_id from their own context
     * so they cannot assign sub-franchises to other companies.
     * Superadmin/system_admin: these values are provided in the request payload.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        // Always force type = 'sub' regardless of what the payload says.
        $merged = ['type' => 'sub'];

        if ($user?->hasRole(Role::SB_OWNER)) {
            $merged['parent_company_id'] = $user->company_id;
            $merged['owner_user_id'] = $user->id;
        }

        $this->merge($merged);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('franchises')->whereNull('deleted_at')],
            'type' => ['required', 'string', 'in:sub'],
            'email' => ['nullable', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'region' => ['nullable', 'string', 'max:255'],
            'parent_company_id' => ['required', 'integer', 'exists:companies,id'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Sub-franchise name is required.',
            'name.unique' => 'A franchise with this name already exists.',
            'parent_company_id.required' => 'Your account is not linked to a company.',
        ];
    }
}

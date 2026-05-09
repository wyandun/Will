<?php

namespace App\Http\Requests\Invitation;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInvitationRequest extends FormRequest
{
    /**
     * Authorization is handled by InvitationController via $this->authorize().
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],

            // superadmin is never assigned through an invitation.
            // Role-level restrictions per inviter type are handled in the
            // next issue; for now any valid non-superadmin role is accepted.
            'role' => [
                'required',
                'string',
                Rule::in([
                    Role::SYSTEM_ADMIN,
                    Role::SYSTEM_ADMIN_READONLY,
                    Role::ADMIN_SM,
                    Role::SB_OWNER,
                    Role::SB_EMPLOYEE,
                    Role::BB_EMPLOYEE,
                    Role::SUB_FRANCHISE_OWNER,
                    Role::SUB_FRANCHISE_ADMIN,
                ]),
            ],
        ];
    }
}

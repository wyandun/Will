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

            'email' => [
                'required',
                'email',
                'max:255',
                // Block accepted accounts at the FormRequest layer for a fast 422
                // before hitting the service. Pending invitations and soft-deleted
                // users still pass through — the service distinguishes those cases
                // and returns the appropriate error message for each.
                Rule::unique('users', 'email')
                    ->whereNotNull('invitation_accepted_at')
                    ->whereNull('deleted_at'),
            ],

            // superadmin is never assigned through an invitation.
            // Role-level restrictions per inviter type are handled in the
            // next issue; for now any valid non-superadmin role is accepted.
            'role' => [
                'required',
                'string',
                Rule::in(Role::invitable()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'invitation.email_already_active',
        ];
    }
}

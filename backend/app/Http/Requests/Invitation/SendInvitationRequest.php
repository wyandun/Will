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
                // Fail fast if the email already belongs to an active user.
                //
                // Conditions are intentionally narrow so that:
                //  - Pending invitations (invitation_accepted_at IS NULL) pass through
                //    and let the service regenerate their token.
                //  - Soft-deleted users (deleted_at IS NOT NULL) also pass through
                //    so the service can return a user-facing error about the deleted account.
                //    (Safely handled by `User::withTrashed()->first()` in InvitationService).
                //  - Only a live, accepted account triggers the 422 at validation time.
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

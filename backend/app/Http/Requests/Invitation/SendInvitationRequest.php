<?php

namespace App\Http\Requests\Invitation;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

            // Required only when a superadmin invites an admin_sm — the superadmin
            // must specify which franchise to assign the new admin to.
            // When an admin_sm sends the invitation, sm_franchise_id is optional
            // because the service already falls back to the inviter's own
            // sm_franchise_id (see InvitationService::send()).
            'sm_franchise_id' => ['nullable', 'integer', 'exists:franchises,id'],
        ];
    }

    /**
     * sm_franchise_id is required only when a superadmin invites an admin_sm.
     * admin_sm inviters are scoped to their own franchise, so the field is
     * optional for them — the service resolves it from the inviter's record.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('sm_franchise_id', 'required', function ($input) {
            return $this->user()?->hasRole(Role::SUPERADMIN)
                && $input->role === Role::ADMIN_SM;
        });
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'invitation.email_already_active',
            'sm_franchise_id.required' => 'Franchise ID is required when inviting an admin.',
            'sm_franchise_id.exists' => 'The selected franchise does not exist.',
        ];
    }
}

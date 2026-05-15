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

            // Required when inviting an admin_sm so the invited user is linked
            // to the correct franchise. Optional for all other roles (the service
            // falls back to the inviter's own sm_franchise_id).
            // 'sometimes' is intentionally absent: withValidator() needs to be able
            // to add 'required' when role=admin_sm, which won't work if the base rule
            // skips validation when the field is absent.
            'sm_franchise_id' => ['nullable', 'integer', 'exists:franchises,id'],
        ];
    }

    /**
     * sm_franchise_id is required when the role is admin_sm.
     * Using withValidator() so the conditional rule runs after base validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('sm_franchise_id', 'required', function ($input) {
            return $input->role === Role::ADMIN_SM;
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

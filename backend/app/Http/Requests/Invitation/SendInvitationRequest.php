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

            // Required when admin_sm invites sb_owner or sb_employee (admin_sm must
            // specify the company). When sb_owner invites sb_employee, the service
            // auto-fills from the inviter's company_id.
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],

            // Required when admin_sm invites sub_franchise_owner or sub_franchise_admin.
            // When sub_franchise_owner invites sub_franchise_admin, the service
            // auto-fills from the inviter's sub_franchise_id.
            'sub_franchise_id' => ['nullable', 'integer', 'exists:franchises,id'],
        ];
    }

    /**
     * Conditional validation:
     * 1. Role hierarchy: the inviter can only invite roles allowed by invitableByRole().
     * 2. sm_franchise_id required when superadmin invites admin_sm.
     * 3. company_id required when admin_sm invites sb_owner or sb_employee.
     * 4. sub_franchise_id required when admin_sm invites sub_franchise_owner or sub_franchise_admin.
     *
     * When sb_owner invites sb_employee, company_id is optional here because
     * InvitationService::send() falls back to the inviter's own company_id.
     * Same pattern for sub_franchise_owner inviting sub_franchise_admin.
     */
    public function withValidator(Validator $validator): void
    {
        // Enforce role hierarchy: inviter can only invite roles in their allowed list.
        $validator->after(function (Validator $v) {
            $inviterRole = $this->user()?->getRoleNames()->first();

            if ($inviterRole && $this->input('role')) {
                $allowed = Role::invitableByRole($inviterRole);

                if (! in_array($this->input('role'), $allowed, true)) {
                    $v->errors()->add('role', 'invitation.role_not_allowed');
                }
            }
        });

        $validator->sometimes('sm_franchise_id', 'required', function ($input) {
            return $this->user()?->hasRole(Role::SUPERADMIN)
                && $input->role === Role::ADMIN_SM;
        });

        // company_id required when admin_sm invites sb_owner or sb_employee.
        // Superadmin can optionally provide it (nullable). sb_owner auto-inherits
        // from their own company_id via InvitationService::send().
        $validator->sometimes('company_id', 'required', function ($input) {
            return $this->user()?->hasRole(Role::ADMIN_SM)
                && in_array($input->role, [Role::SB_OWNER, Role::SB_EMPLOYEE], true);
        });

        // sub_franchise_id required when admin_sm invites sub_franchise_owner or
        // sub_franchise_admin. Superadmin can optionally provide it.
        // sub_franchise_owner auto-inherits via InvitationService::send().
        $validator->sometimes('sub_franchise_id', 'required', function ($input) {
            return $this->user()?->hasRole(Role::ADMIN_SM)
                && in_array($input->role, [Role::SUB_FRANCHISE_OWNER, Role::SUB_FRANCHISE_ADMIN], true);
        });
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'invitation.email_already_active',
            'sm_franchise_id.required' => 'invitation.franchise_id_required',
            'sm_franchise_id.exists' => 'invitation.franchise_not_found',
            'company_id.required' => 'invitation.company_id_required',
            'company_id.exists' => 'invitation.company_not_found',
            'sub_franchise_id.required' => 'invitation.sub_franchise_id_required',
            'sub_franchise_id.exists' => 'invitation.sub_franchise_not_found',
        ];
    }
}

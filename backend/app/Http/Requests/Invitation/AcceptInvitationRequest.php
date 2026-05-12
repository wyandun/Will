<?php

namespace App\Http\Requests\Invitation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    /**
     * Public endpoint — no authenticated user required.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->uncompromised()],
        ];
    }
}

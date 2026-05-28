<?php

namespace App\Http\Requests\FranchiseAdmin;

use App\Http\Requests\AuthenticatedRequest;

class DestroyFranchiseAdminRequest extends AuthenticatedRequest
{
    // authorize() is inherited from AuthenticatedRequest: returns true when the user
    // is authenticated (i.e. $this->user() !== null). Business-level authorization
    // (superadmin only) is enforced by the 'deleteFranchiseAdmin' policy check in
    // the controller via $this->authorize(). No rules needed — this route has no body.
    public function rules(): array
    {
        return [];
    }
}

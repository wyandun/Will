<?php

namespace App\Http\Requests\FranchiseAdmin;

use App\Http\Requests\AuthenticatedRequest;

class RestoreFranchiseAdminRequest extends AuthenticatedRequest
{
    // authorize() is inherited from AuthenticatedRequest: returns true when the user
    // is authenticated. Business-level authorization (superadmin only) is enforced by
    // the 'restoreFranchiseAdmin' policy check in the controller. No rules needed —
    // restore receives only a route segment (user ID), no request body.
    public function rules(): array
    {
        return [];
    }
}

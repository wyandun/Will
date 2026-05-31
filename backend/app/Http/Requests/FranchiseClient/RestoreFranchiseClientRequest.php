<?php

namespace App\Http\Requests\FranchiseClient;

use App\Http\Requests\AuthenticatedRequest;

class RestoreFranchiseClientRequest extends AuthenticatedRequest
{
    // authorize() is inherited from AuthenticatedRequest: returns true when the user
    // is authenticated. Business-level authorization (superadmin + admin_sm) is enforced
    // by the 'restoreFranchiseClient' policy check in the controller. No rules needed —
    // restore receives only a route segment (user ID), no request body.
    public function rules(): array
    {
        return [];
    }
}

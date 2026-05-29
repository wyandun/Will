<?php

namespace App\Http\Requests\FranchiseClient;

use App\Http\Requests\AuthenticatedRequest;

class DestroyFranchiseClientRequest extends AuthenticatedRequest
{
    // authorize() is inherited from AuthenticatedRequest: returns true when the user
    // is authenticated. Business-level authorization (superadmin + admin_sm) is enforced
    // by the 'deleteFranchiseClient' policy check in the controller via $this->authorize().
    // No rules needed — this route has no request body.
    public function rules(): array
    {
        return [];
    }
}

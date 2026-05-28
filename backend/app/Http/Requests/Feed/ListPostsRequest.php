<?php

namespace App\Http\Requests\Feed;

use App\Http\Requests\AuthenticatedRequest;

class ListPostsRequest extends AuthenticatedRequest
{
    /**
     * Validation rules.
     *
     * NOTE: per_page is left unconstrained here on purpose. The controller
     * clamps it to the 5..50 range (default 10) for backward compatibility
     * with clients that may send values outside that range — those should
     * be silently coerced, not rejected with a 422.
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer'],
        ];
    }
}

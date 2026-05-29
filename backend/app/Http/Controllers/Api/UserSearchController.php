<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\SearchUsersRequest;
use App\Services\UserSearchService;
use Illuminate\Http\JsonResponse;

class UserSearchController extends Controller
{
    public function __construct(private UserSearchService $userSearchService) {}

    /**
     * Lightweight user lookup for the "Add Guests" search in the calendar event modal.
     *
     * Scoping:
     *   - superadmin / system_admin: all users
     *   - everyone else: only users in their own franchise (sm_franchise_id match)
     *
     * Returns up to 10 results — UI is for picking, not browsing.
     */
    public function __invoke(SearchUsersRequest $request): JsonResponse
    {
        $q = (string) $request->query('q', '');

        $users = $this->userSearchService->search($request->user(), $q);

        return response()->json([
            'success' => true,
            'data' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
            ]),
        ]);
    }
}

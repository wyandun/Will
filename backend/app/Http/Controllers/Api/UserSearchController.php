<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    /**
     * Lightweight user lookup for the "Add Guests" search in the calendar event modal.
     *
     * Scoping:
     *   - superadmin / system_admin: all users
     *   - everyone else: only users in their own franchise (sm_franchise_id match)
     *
     * Returns up to 10 results — UI is for picking, not browsing.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $q = trim((string) $request->query('q', ''));
        $authUser = $request->user();

        $query = User::query()
            ->select(['id', 'name', 'email', 'avatar_path', 'sm_franchise_id'])
            ->whereNotNull('invitation_accepted_at')
            ->where('id', '!=', $authUser->id);

        if (! $authUser->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])) {
            $query->where('sm_franchise_id', $authUser->sm_franchise_id);
        }

        if ($q !== '') {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $q);
            $query->where(function ($qq) use ($escaped) {
                $qq->where('name', 'like', "%{$escaped}%")
                    ->orWhere('email', 'like', "%{$escaped}%");
            });
        }

        $users = $query->orderBy('name')->limit(10)->get();

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

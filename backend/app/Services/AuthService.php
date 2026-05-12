<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Attempt to authenticate a user and return a Sanctum token plus their
     * role and module permissions loaded from the user_permissions table.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: array, token: string, role: string|null, permissions: array}
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        if (! Auth::attempt($credentials)) {
            Log::warning('Failed login attempt', ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // Multi-session is intentionally allowed: revoking all tokens here would
        // silently log out other open tabs or devices. Token revocation is handled
        // explicitly in logout() instead.
        $token = $user->createToken('auth_token')->plainTextToken;

        // Load module permissions from user_permissions table.
        $permissions = $user->userPermissions()
            ->get(['module', 'can_read', 'can_write'])
            ->map(fn ($p) => [
                'module' => $p->module,
                'can_read' => (bool) $p->can_read,
                'can_write' => (bool) $p->can_write,
            ])
            ->values()
            ->all();

        // Spatie role — users have exactly one role in this system.
        $role = $user->getRoleNames()->first();

        Log::info('User logged in', ['user_id' => $user->id, 'role' => $role]);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
                // Needed by the frontend to pre-fill the franchise dropdown for admin_sm.
                'sm_franchise_id' => $user->sm_franchise_id ?? null,
            ],
            'token' => $token,
            'role' => $role,
            'permissions' => $permissions,
        ];
    }

    /**
     * Return the current authenticated user's data without reissuing a token.
     *
     * @return array{user: array, token: null, role: string|null, permissions: array}
     */
    public function me(User $user): array
    {
        // Load module permissions from user_permissions table.
        $permissions = $user->userPermissions()
            ->get(['module', 'can_read', 'can_write'])
            ->map(fn ($p) => [
                'module' => $p->module,
                'can_read' => (bool) $p->can_read,
                'can_write' => (bool) $p->can_write,
            ])
            ->values()
            ->all();

        // Spatie role — users have exactly one role in this system.
        $role = $user->getRoleNames()->first();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
                // Needed by the frontend to pre-fill the franchise dropdown for admin_sm.
                'sm_franchise_id' => $user->sm_franchise_id ?? null,
            ],
            'token' => null,
            'role' => $role,
            'permissions' => $permissions,
        ];
    }

    /**
     * Revoke the current Sanctum token for the given user (single-session logout).
     *
     * Multi-session is intentionally allowed, so only the token used in this
     * request is revoked — other open tabs or devices remain authenticated.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();

        Log::info('User logged out', ['user_id' => $user->id]);
    }
}

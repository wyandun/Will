<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPermission;
use App\Notifications\UserInvitationNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Handles the full invitation lifecycle: send, resend, verify, accept, revoke.
 *
 * SECURITY NOTES:
 *  - invitation_token is stored in plaintext. Given the 64-char entropy and
 *    rate limiting on public endpoints (throttle:invitation), the brute-force
 *    risk is low. If token hashing is ever required, store hash($token) in
 *    send()/regenerateAndNotify() and use Hash::check() in verify()/accept().
 *  - The Sanctum token is returned in the JSON response body (stored in
 *    localStorage by the frontend). This is an intentional design decision
 *    for SPA compatibility — do not also set a cookie (double-issuance).
 */
class InvitationService
{
    private const EXPIRY_DAYS = 7;

    /**
     * Send an invitation to the given email address.
     *
     * Decision tree:
     *  1. Email already has an accepted account → validation error.
     *  2. Email has a soft-deleted account      → validation error (support needed).
     *  3. Email has a pending invitation        → regenerate token & resend.
     *  4. Email is brand new                    → create user, assign role, notify.
     *
     * @param  array{name: string, email: string, role: string}  $data
     * @return array{user: User, activation_url: string|null}
     *
     * @throws ValidationException
     */
    public function send(array $data, User $invitedBy): array
    {
        $existing = User::withTrashed()->where('email', $data['email'])->first();

        if ($existing) {
            if ($existing->invitation_accepted_at) {
                throw ValidationException::withMessages([
                    'email' => ['invitation.email_already_active'],
                ]);
            }

            if ($existing->trashed()) {
                throw ValidationException::withMessages([
                    'email' => ['invitation.email_deleted_account'],
                ]);
            }

            // Pending invitation → regenerate and resend
            return $this->regenerateAndNotify($existing, $invitedBy, $data['role']);
        }

        // Brand-new user
        // CSPRNG via random_bytes(): 64 base-62 chars ≈ 381 bits entropy.
        // Rate-limited by throttle:invitation (10/min per IP).
        $token = Str::random(64);

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                // Placeholder password — overwritten when the user accepts the invitation.
                'password' => Hash::make(Str::random(32)),
                'invitation_expires_at' => now()->addDays(self::EXPIRY_DAYS),
                'sm_franchise_id' => $invitedBy->sm_franchise_id,
            ]);

            // Security: invitation_token and inviter_id are not mass-assignable — set explicitly
            // to prevent token injection via any mass-assignment path.
            $user->invitation_token = $token;
            $user->inviter_id = $invitedBy->id;
            $user->save();
        } catch (QueryException $e) {
            // Race condition: another request created an active user with the same email
            // between validation and this write. Detect duplicate key errors (23000 for SQLite,
            // 23505 for Postgres) and convert to a user-facing validation error.
            $code = $e->errorInfo[0] ?? '';
            if (in_array($code, ['23000', '23505'], true)) {
                throw ValidationException::withMessages([
                    'email' => ['invitation.email_already_active'],
                ]);
            }
            throw $e;
        }

        $user->assignRole($data['role']);

        return $this->notify($user, $invitedBy);
    }

    /**
     * Regenerate the token for an existing pending invitation and re-send the email.
     */
    public function resendById(User $user, User $invitedBy): array
    {
        if ($user->invitation_accepted_at || ! $user->invitation_token) {
            abort(422, 'invitation.not_pending');
        }

        return $this->regenerateAndNotify($user, $invitedBy);
    }

    /**
     * Verify that a token exists, belongs to a pending invitation, and has not expired.
     *
     * Both failure paths (invalid or expired token) return 404 with the same message
     * to prevent enumeration attacks that could distinguish between "never existed"
     * and "once existed but expired."
     *
     * @throws HttpException 404
     */
    public function verify(string $token): User
    {
        $user = User::where('invitation_token', $token)
            ->whereNull('invitation_accepted_at')
            ->first();

        if (! $user) {
            abort(404, 'invitation.invalid_link');
        }

        if ($user->invitation_expires_at && $user->invitation_expires_at->isPast()) {
            abort(404, 'invitation.invalid_link');
        }

        return $user;
    }

    /**
     * Accept an invitation: verify the token, set the user's password, mark the
     * account as active, and return a Sanctum token for auto-login on the frontend.
     *
     * The verify + write are wrapped in a single DB transaction with a row-level
     * lock so that concurrent requests cannot both pass the expiry check and then
     * both activate the same account (TOCTOU race condition).
     *
     * @return array{user: array, token: string, role: string, permissions: Collection}
     *
     * @throws HttpException 404 | 410
     */
    public function accept(string $token, string $password): array
    {
        return DB::transaction(function () use ($token, $password) {
            // Lock the row for the duration of the transaction so a second
            // concurrent request cannot slip through between verify and write.
            $user = User::where('invitation_token', $token)
                ->whereNull('invitation_accepted_at')
                ->lockForUpdate()
                ->first();

            if (! $user) {
                abort(404, 'invitation.invalid_link');
            }

            if ($user->invitation_expires_at && $user->invitation_expires_at->isPast()) {
                abort(404, 'invitation.invalid_link');
            }

            // Security: invitation_token is not mass-assignable. All security-
            // sensitive fields are set via explicit assignment in a single save().
            $user->password = Hash::make($password);
            $user->invitation_token = null;
            $user->invitation_expires_at = null;
            $user->invitation_accepted_at = now();
            $user->email_verified_at = now();
            $user->save();

            // Revoke any stale tokens (e.g. from a previous partial accept attempt)
            // before issuing a fresh one.
            $user->tokens()->delete();

            // Scope: ['*'] grants full abilities (standard for primary session tokens).
            // Expiry: uses sanctum.expiration config (default 1440 min = 24 h).
            $plainToken = $user->createToken(
                'portal',
                ['*'],
                now()->addMinutes((int) config('sanctum.expiration', 1440))
            )->plainTextToken;
            $role = $user->getRoleNames()->first() ?? '';
            $permissions = UserPermission::where('user_id', $user->id)->get();

            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_path' => $user->avatar_path ?? null,
                    'sm_franchise_id' => $user->sm_franchise_id ?? null,
                ],
                'token' => $plainToken,
                'role' => $role,
                'permissions' => $permissions,
            ];
        });
    }

    /**
     * Revoke a pending invitation by soft-deleting the placeholder user record.
     *
     * Explicitly nulls the invitation_token before soft-delete (defense-in-depth).
     * While Eloquent's SoftDeletes trait automatically scopes queries to exclude
     * soft-deleted rows, explicitly nullifying the token prevents any latent leakage
     * if the row is ever examined directly.
     */
    public function revoke(User $user): void
    {
        if ($user->invitation_accepted_at || ! $user->invitation_token) {
            abort(422, 'invitation.not_pending');
        }

        // Security: invitation_token is not mass-assignable — set explicitly.
        $user->invitation_token = null;
        $user->invitation_expires_at = null;
        $user->save();

        $user->tokens()->delete();
        $user->delete();
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * Generate a fresh token, persist it, and fire the notification.
     * Optionally re-assign a role (used when resending after role change).
     */
    private function regenerateAndNotify(User $user, User $invitedBy, ?string $role = null): array
    {
        // CSPRNG via random_bytes(): 64 base-62 chars ≈ 381 bits entropy.
        $token = Str::random(64);

        // Security: invitation_token is not mass-assignable — set explicitly.
        $user->invitation_token = $token;
        $user->invitation_expires_at = now()->addDays(self::EXPIRY_DAYS);
        $user->inviter_id = $invitedBy->id;
        $user->save();

        if ($role && ! $user->hasRole($role)) {
            $user->syncRoles([$role]);
        }

        return $this->notify($user, $invitedBy);
    }

    private function notify(User $user, User $invitedBy): array
    {
        $activationUrl = $this->buildActivationUrl($user->invitation_token);
        $roleName = $user->getRoleNames()->first() ?? 'usuario';

        $user->notify(new UserInvitationNotification($activationUrl, $invitedBy->name, $roleName));

        return [
            'user' => $user->load('roles'),
            // Only expose the raw URL in local/testing so devs can test
            // without a real mail service. Do not expose in staging/production.
            'activation_url' => app()->environment(['local', 'testing']) ? $activationUrl : null,
        ];
    }

    private function buildActivationUrl(string $token): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        return "{$frontendUrl}/invite/{$token}";
    }
}

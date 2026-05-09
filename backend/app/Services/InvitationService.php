<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPermission;
use App\Notifications\UserInvitationNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
                    'email' => ['Este correo ya pertenece a un usuario activo en el portal.'],
                ]);
            }

            if ($existing->trashed()) {
                throw ValidationException::withMessages([
                    'email' => ['Este correo pertenece a una cuenta eliminada. Contacta a soporte para reactivarla.'],
                ]);
            }

            // Pending invitation → regenerate and resend
            return $this->regenerateAndNotify($existing, $invitedBy, $data['role']);
        }

        // Brand-new user
        $token = Str::random(64);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            // Placeholder password — overwritten when the user accepts the invitation.
            'password' => Hash::make(Str::random(32)),
            'invitation_token' => $token,
            'invitation_expires_at' => now()->addDays(self::EXPIRY_DAYS),
            'inviter_id' => $invitedBy->id,
        ]);

        $user->assignRole($data['role']);

        return $this->notify($user, $invitedBy);
    }

    /**
     * Regenerate the token for an existing pending invitation and re-send the email.
     */
    public function resendById(User $user, User $invitedBy): array
    {
        if ($user->invitation_accepted_at) {
            abort(422, 'La invitación ya fue aceptada; no es posible reenviarla.');
        }

        return $this->regenerateAndNotify($user, $invitedBy);
    }

    /**
     * Verify that a token exists, belongs to a pending invitation, and has not expired.
     *
     * @throws HttpException 404 | 410
     */
    public function verify(string $token): User
    {
        $user = User::where('invitation_token', $token)
            ->whereNull('invitation_accepted_at')
            ->first();

        if (! $user) {
            abort(404, 'El enlace de invitación no es válido.');
        }

        if ($user->invitation_expires_at && $user->invitation_expires_at->isPast()) {
            abort(410, 'El enlace de invitación ha expirado. Pedí que te reenvíen la invitación.');
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
     * @return array{user: User, token: string, role: string, permissions: Collection}
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
                abort(404, 'El enlace de invitación no es válido.');
            }

            if ($user->invitation_expires_at && $user->invitation_expires_at->isPast()) {
                abort(410, 'El enlace de invitación ha expirado. Pide que te reenvíen la invitación.');
            }

            $user->update([
                'password' => Hash::make($password),
                'invitation_token' => null,
                'invitation_expires_at' => null,
                'invitation_accepted_at' => now(),
            ]);

            // email_verified_at is not in $fillable (it is framework-managed),
            // so we set it directly and persist with save().
            $user->email_verified_at = now();
            $user->save();

            $plainToken = $user->createToken('portal')->plainTextToken;
            $role = $user->getRoleNames()->first() ?? '';
            $permissions = UserPermission::where('user_id', $user->id)->get();

            return [
                'user' => $user->fresh(),
                'token' => $plainToken,
                'role' => $role,
                'permissions' => $permissions,
            ];
        });
    }

    /**
     * Revoke a pending invitation by soft-deleting the placeholder user record.
     */
    public function revoke(User $user): void
    {
        if ($user->invitation_accepted_at) {
            abort(422, 'No se puede revocar una invitación que ya fue aceptada.');
        }

        $user->delete();
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * Generate a fresh token, persist it, and fire the notification.
     * Optionally re-assign a role (used when resending after role change).
     */
    private function regenerateAndNotify(User $user, User $invitedBy, ?string $role = null): array
    {
        $token = Str::random(64);

        $user->update([
            'invitation_token' => $token,
            'invitation_expires_at' => now()->addDays(self::EXPIRY_DAYS),
            'inviter_id' => $invitedBy->id,
        ]);

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
            // Only expose the raw URL outside production so devs can test
            // without a real mail service (check storage/logs/laravel.log too).
            'activation_url' => app()->isProduction() ? null : $activationUrl,
        ];
    }

    private function buildActivationUrl(string $token): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        return "{$frontendUrl}/invite/{$token}";
    }
}

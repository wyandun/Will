<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /**
     * Update the authenticated user's profile fields.
     *
     * If the email is being changed, email_verified_at is reset so the user
     * must re-verify their address (re-verification flow is TODO).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->fill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
            // TODO: send email verification notification once email verification flow is implemented
        }

        $user->save();

        return $user->fresh();
    }

    /**
     * Change the user's password.
     *
     * The 'password' cast on the User model already hashes via the model
     * mutator, but we hash explicitly here for clarity and to remain
     * independent of the model's cast configuration.
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->update(['password' => Hash::make($newPassword)]);
    }

    /**
     * Upload and persist a new avatar for the user.
     *
     * On a successful DB update the previous avatar file is deleted from
     * disk. If the DB update fails the newly stored file is removed so we
     * do not leave orphaned uploads behind.
     */
    public function uploadAvatar(User $user, UploadedFile $file): User
    {
        $extension = $file->extension();
        $filename = $user->id.'_'.bin2hex(random_bytes(8)).'.'.$extension;
        $path = $file->storeAs('avatars', $filename, 'public');

        $oldPath = $user->avatar_path;

        try {
            DB::transaction(function () use ($user, $path): void {
                $user->update(['avatar_path' => $path]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return $user->fresh();
    }
}

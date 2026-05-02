<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Return the authenticated user's profile data.
     *
     * GET /api/v1/profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
            'message' => 'OK.',
        ]);
    }

    /**
     * Update name, email, and optional profile fields.
     *
     * PATCH /api/v1/profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user->fresh()),
            'message' => 'Profile updated successfully.',
        ]);
    }

    /**
     * Change the authenticated user's password.
     *
     * PATCH /api/v1/profile/password
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Current password is incorrect.',
                'errors'  => ['current_password' => ['The provided password does not match our records.']],
            ], 422);
        }

        // Assign directly to bypass the 'hashed' cast, which would double-hash.
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Upload and replace the authenticated user's avatar.
     *
     * POST /api/v1/profile/avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user      = $request->user();
        $extension = $request->file('avatar')->getClientOriginalExtension();
        $path      = $request->file('avatar')->storeAs(
            'avatars',
            "{$user->id}.{$extension}",
            'public'
        );

        // Remove the old avatar file if it differs from the new one.
        if ($user->avatar_path && $user->avatar_path !== $path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => $path]);

        return response()->json([
            'success' => true,
            'data'    => ['avatar_url' => Storage::disk('public')->url($path)],
            'message' => 'Avatar uploaded successfully.',
        ]);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Serialize a user into the profile payload.
     */
    private function formatUser(\App\Models\User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'job_title'  => $user->job_title,
            'bio'        => $user->bio,
            'birth_date' => $user->birth_date?->toDateString(),
            'avatar_url' => $user->avatar_path
                ? Storage::disk('public')->url($user->avatar_path)
                : null,
            'role'       => $user->getRoleNames()->first(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Resources\UserProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Return the authenticated user's profile data.
     *
     * GET /api/v1/profile
     */
    public function show(Request $request): UserProfileResource
    {
        return new UserProfileResource($request->user());
    }

    /**
     * Update name, email, and optional profile fields.
     *
     * PATCH /api/v1/profile
     */
    public function update(UpdateProfileRequest $request): UserProfileResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return new UserProfileResource($user->fresh());
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
                'data' => null,
                'message' => 'Current password is incorrect.',
                'errors' => ['current_password' => ['The provided password does not match our records.']],
            ], 422);
        }

        DB::transaction(function () use ($user, $request): void {
            // Assign directly to bypass the 'hashed' cast, which would double-hash.
            $user->password = Hash::make($request->new_password);
            $user->save();
        });

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Upload and replace the authenticated user's avatar.
     *
     * POST /api/v1/profile/avatar
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $file = $request->file('avatar');
        $extension = strtolower($file->getClientOriginalExtension());
        $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (! in_array($extension, $validExtensions, true)) {
            abort(422, 'Invalid image type.');
        }

        $user = $request->user();
        $path = $file->storeAs(
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
            'data' => ['avatar_url' => $user->fresh()->avatar_url],
            'message' => 'Avatar uploaded successfully.',
        ]);
    }
}

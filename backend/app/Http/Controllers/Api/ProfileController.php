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
        $data = $request->validated();

        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->update($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
            $user->save();
            // TODO: send email verification notification once email verification flow is implemented
        }

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

        $user->update(['password' => $request->new_password]);

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
        $user = $request->user();
        $file = $request->file('avatar');
        $extension = $file->extension();
        $filename = $user->id.'_'.bin2hex(random_bytes(8)).'.'.$extension;
        $path = $file->storeAs('avatars', $filename, 'public');

        try {
            DB::transaction(function () use ($user, $path): void {
                $oldPath = $user->avatar_path;
                $user->update(['avatar_path' => $path]);
                if ($oldPath && $oldPath !== $path) {
                    Storage::disk('public')->delete($oldPath);
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        $user->refresh();

        return response()->json([
            'success' => true,
            'data' => ['avatar_url' => $user->avatar_url],
            'message' => 'Avatar uploaded successfully.',
        ]);
    }
}

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
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Get(
        path: '/profile',
        tags: ['Profile'],
        summary: 'Obtener perfil del usuario autenticado',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Datos del perfil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Super Admin'),
                        new OA\Property(property: 'email', type: 'string', example: 'admin@smportal.com'),
                        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+1 555 0000'),
                        new OA\Property(property: 'job_title', type: 'string', nullable: true, example: 'CEO'),
                        new OA\Property(property: 'bio', type: 'string', nullable: true, example: 'Bio del usuario'),
                        new OA\Property(property: 'birth_date', type: 'string', format: 'date', nullable: true, example: '1990-01-01'),
                        new OA\Property(property: 'avatar_url', type: 'string', nullable: true, example: 'http://localhost/storage/avatars/1_abc.jpg'),
                        new OA\Property(property: 'role', type: 'string', example: 'superadmin'),
                        new OA\Property(property: 'email_verified', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function show(Request $request): UserProfileResource
    {
        return new UserProfileResource($request->user());
    }

    #[OA\Patch(
        path: '/profile',
        tags: ['Profile'],
        summary: 'Actualizar perfil del usuario',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Super Admin'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@smportal.com'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+1 555 0000'),
                    new OA\Property(property: 'job_title', type: 'string', nullable: true, example: 'CEO'),
                    new OA\Property(property: 'bio', type: 'string', nullable: true, example: 'Bio del usuario'),
                    new OA\Property(property: 'birth_date', type: 'string', format: 'date', nullable: true, example: '1990-01-01'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Perfil actualizado'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function update(UpdateProfileRequest $request): UserProfileResource
    {
        $user = $request->user();
        $data = $request->validated();

        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->fill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
            // TODO: send email verification notification once email verification flow is implemented
        }

        $user->save();

        return new UserProfileResource($user->fresh());
    }

    #[OA\Patch(
        path: '/profile/password',
        tags: ['Profile'],
        summary: 'Cambiar contraseña',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password', 'new_password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', example: 'password'),
                    new OA\Property(property: 'new_password', type: 'string', example: 'nuevaPassword123'),
                    new OA\Property(property: 'new_password_confirmation', type: 'string', example: 'nuevaPassword123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contraseña actualizada'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 422, description: 'Contraseña actual incorrecta o confirmación no coincide'),
        ]
    )]
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['password' => $request->validated('new_password')]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Password updated successfully.',
        ]);
    }

    #[OA\Post(
        path: '/profile/avatar',
        tags: ['Profile'],
        summary: 'Subir avatar',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['avatar'],
                    properties: [
                        new OA\Property(
                            property: 'avatar',
                            type: 'string',
                            format: 'binary',
                            description: 'JPG o PNG, máx 5MB, máx 800x800px'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Avatar subido',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object',
                            properties: [
                                new OA\Property(property: 'avatar_url', type: 'string', example: 'http://localhost/storage/avatars/1_abc.jpg'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 422, description: 'Archivo inválido (solo JPG/PNG, máx 5MB, máx 800x800px)'),
        ]
    )]
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('avatar');
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

        $user->refresh();

        return response()->json([
            'success' => true,
            'data' => ['avatar_url' => $user->avatar_url],
            'message' => 'Avatar uploaded successfully.',
        ]);
    }
}

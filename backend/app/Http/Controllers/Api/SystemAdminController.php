<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\SystemAdmin\StoreSystemAdminRequest;
use App\Http\Requests\SystemAdmin\UpdateSystemAdminRequest;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class SystemAdminController extends Controller
{
    #[OA\Get(
        path: '/system-admins',
        tags: ['System Admins'],
        summary: 'Listar administradores de sistema',
        description: 'Retorna todos los usuarios con rol system_admin o system_admin_readonly. Solo superadmin puede acceder.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de administradores de sistema',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 2),
                                    new OA\Property(property: 'name', type: 'string', example: 'María López'),
                                    new OA\Property(property: 'email', type: 'string', example: 'maria@strategicmates.com'),
                                    new OA\Property(
                                        property: 'roles',
                                        type: 'array',
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 2),
                                                new OA\Property(property: 'name', type: 'string', example: 'system_admin'),
                                            ]
                                        )
                                    ),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(): JsonResponse
    {
        $this->authorize('viewAnySystemAdmin', User::class);

        $users = User::role([Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])
            ->with('roles')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    #[OA\Post(
        path: '/system-admins',
        tags: ['System Admins'],
        summary: 'Crear un administrador de sistema',
        description: 'Crea un usuario con rol system_admin o system_admin_readonly y le asigna permisos de lectura/escritura en todos los módulos según el rol. Solo superadmin puede crear admins de sistema.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'María López'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'maria@strategicmates.com'),
                    new OA\Property(property: 'password', type: 'string', minLength: 12, example: 'SecurePass123!', description: 'Mínimo 12 caracteres'),
                    new OA\Property(property: 'role', type: 'string', enum: ['system_admin', 'system_admin_readonly'], example: 'system_admin'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Admin de sistema creado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'message', type: 'string', example: 'system_admin.created_success'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(
                response: 422,
                description: 'Error de validación (email duplicado, password débil, rol inválido)',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function store(StoreSystemAdminRequest $request): JsonResponse
    {
        $this->authorize('createSystemAdmin', User::class);

        $validated = $request->validated();
        $roleName = $validated['role'];

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($roleName);

        // Assign module permissions based on role
        UserPermission::syncForRole($user->id, $roleName);

        return response()->json([
            'success' => true,
            'data' => $user->load('roles'),
            'message' => 'system_admin.created_success',
        ], 201);
    }

    #[OA\Patch(
        path: '/system-admins/{id}',
        tags: ['System Admins'],
        summary: 'Actualizar un administrador de sistema',
        description: 'Actualiza nombre, email, contraseña y/o rol. No se puede modificar al superadmin ni a uno mismo por este endpoint. Los permisos de módulo se re-sincronizan según el nuevo rol.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del usuario admin de sistema',
                schema: new OA\Schema(type: 'integer', example: 2)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'María López'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'maria@strategicmates.com'),
                    new OA\Property(property: 'password', type: 'string', minLength: 12, nullable: true, example: null, description: 'Omitir para no cambiar la contraseña'),
                    new OA\Property(property: 'role', type: 'string', enum: ['system_admin', 'system_admin_readonly'], example: 'system_admin_readonly'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admin de sistema actualizado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'message', type: 'string', example: 'system_admin.updated_success'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(
                response: 422,
                description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function update(UpdateSystemAdminRequest $request, User $systemAdmin): JsonResponse
    {
        $this->authorize('updateSystemAdmin', $systemAdmin);

        // Disallow modifying the superadmin via this endpoint
        if ($systemAdmin->hasRole(Role::SUPERADMIN)) {
            abort(403, 'system_admins.error_superadmin');
        }

        abort_unless(
            $systemAdmin->hasAnyRole([Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY]),
            403
        );

        $validated = $request->validated();
        $roleName = $validated['role'];

        $systemAdmin->name = $validated['name'];
        $systemAdmin->email = $validated['email'];
        if (! empty($validated['password'])) {
            $systemAdmin->password = Hash::make($validated['password']);
        }
        $systemAdmin->save();

        $systemAdmin->syncRoles([$roleName]);

        UserPermission::syncForRole($systemAdmin->id, $roleName);

        return response()->json([
            'success' => true,
            'data' => $systemAdmin->load('roles'),
            'message' => 'system_admin.updated_success',
        ]);
    }

    #[OA\Delete(
        path: '/system-admins/{id}',
        tags: ['System Admins'],
        summary: 'Eliminar un administrador de sistema',
        description: 'Elimina el usuario y sus permisos de módulo. No se puede eliminar al superadmin ni al propio usuario autenticado.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del usuario admin de sistema',
                schema: new OA\Schema(type: 'integer', example: 2)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admin de sistema eliminado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                        new OA\Property(property: 'message', type: 'string', example: 'system_admin.deleted_success'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(User $systemAdmin): JsonResponse
    {
        $this->authorize('deleteSystemAdmin', $systemAdmin);

        if ($systemAdmin->hasRole(Role::SUPERADMIN)) {
            abort(403, 'system_admins.error_superadmin');
        }

        abort_unless(
            $systemAdmin->hasAnyRole([Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY]),
            403
        );

        // Prevent deleting oneself
        if (auth()->id() === $systemAdmin->id) {
            abort(403, 'system_admins.error_self_delete');
        }

        // Cleanup permissions and delete
        UserPermission::where('user_id', $systemAdmin->id)->delete();
        $systemAdmin->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'system_admin.deleted_success',
        ]);
    }
}

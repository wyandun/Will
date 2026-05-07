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

class SystemAdminController extends Controller
{
    private const ALL_MODULES = [
        'feed',
        'contracts',
        'repository',
        'processes',
        'accounting',
        'inventory',
        'tracking',
        'catalog',
        'calendar',
    ];

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
        $canWrite = $roleName === Role::SYSTEM_ADMIN;

        foreach (self::ALL_MODULES as $module) {
            UserPermission::create([
                'user_id' => $user->id,
                'module' => $module,
                'can_read' => true,
                'can_write' => $canWrite,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $user->load('roles'),
            'message' => 'system_admin.created_success',
        ], 201);
    }

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

        $canWrite = $roleName === Role::SYSTEM_ADMIN;
        foreach (self::ALL_MODULES as $module) {
            UserPermission::updateOrCreate(
                ['user_id' => $systemAdmin->id, 'module' => $module],
                ['can_read' => true, 'can_write' => $canWrite]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $systemAdmin->load('roles'),
            'message' => 'system_admin.updated_success',
        ]);
    }

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

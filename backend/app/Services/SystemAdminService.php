<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SystemAdminService
{
    /**
     * List all users with the system_admin or system_admin_readonly role.
     *
     * @return Collection<int, User>
     */
    public function list(): Collection
    {
        return User::role([Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])
            ->with('roles')
            ->get();
    }

    /**
     * Create a system admin and assign the module permissions for its role.
     *
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole($data['role']);
            UserPermission::syncForRole($user->id, $data['role']);

            return $user->load('roles');
        });
    }

    /**
     * Update an existing system admin.
     *
     * The role guards (cannot modify SUPERADMIN, target must be one of the
     * system admin roles) live in the service so policy authorization stays
     * in the controller layer.
     *
     * @param  array{name: string, email: string, password?: string|null, role: string}  $data
     */
    public function update(User $systemAdmin, array $data): User
    {
        // Disallow modifying the superadmin via this flow.
        if ($systemAdmin->hasRole(Role::SUPERADMIN)) {
            abort(403, 'system_admins.error_superadmin');
        }

        abort_unless(
            $systemAdmin->hasAnyRole([Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY]),
            403
        );

        $roleName = $data['role'];

        $systemAdmin->name = $data['name'];
        $systemAdmin->email = $data['email'];
        if (! empty($data['password'])) {
            $systemAdmin->password = Hash::make($data['password']);
        }
        $systemAdmin->save();

        $systemAdmin->syncRoles([$roleName]);
        UserPermission::syncForRole($systemAdmin->id, $roleName);

        return $systemAdmin->load('roles');
    }

    /**
     * Delete a system admin and remove its module permissions.
     *
     * Guards: cannot delete a SUPERADMIN, cannot delete oneself, and the
     * target must actually be one of the system admin roles.
     */
    public function delete(User $systemAdmin, int $authUserId): void
    {
        if ($systemAdmin->hasRole(Role::SUPERADMIN)) {
            abort(403, 'system_admins.error_superadmin');
        }

        abort_unless(
            $systemAdmin->hasAnyRole([Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY]),
            403
        );

        if ($authUserId === $systemAdmin->id) {
            abort(403, 'system_admins.error_self_delete');
        }

        DB::transaction(function () use ($systemAdmin) {
            UserPermission::where('user_id', $systemAdmin->id)->delete();
            $systemAdmin->delete();
        });
    }
}

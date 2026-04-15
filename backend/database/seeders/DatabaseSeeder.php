<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * All portal module keys that can be granted to a user.
     */
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

    public function run(): void
    {
        $this->createSuperAdmin();
    }

    /**
     * Create the default superadmin account used for initial access.
     * Credentials are read from environment variables (SUPERADMIN_EMAIL /
     * SUPERADMIN_PASSWORD) with safe local-dev fallbacks.
     *
     * This method is fully idempotent — safe to run on every deploy.
     * If the superadmin already exists it is updated, not duplicated.
     * A stale Spatie permission cache is flushed before any role operation
     * to prevent RoleDoesNotExist exceptions on re-deploys.
     */
    private function createSuperAdmin(): void
    {
        // Flush Spatie's in-memory and cache-store permission registry.
        // Required on re-deploys where Redis may hold a stale snapshot of
        // the roles table taken before the current migration run.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure the role row exists before any assignment attempt.
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $email    = env('SUPERADMIN_EMAIL', 'admin@smportal.com');
        $password = env('SUPERADMIN_PASSWORD', 'password');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make($password),
            ]
        );

        // syncRoles is idempotent — replaces whatever role the user had before.
        $user->syncRoles(['superadmin']);

        // Grant full read + write access to every module.
        foreach (self::ALL_MODULES as $module) {
            UserPermission::updateOrCreate(
                ['user_id' => $user->id, 'module' => $module],
                ['can_read' => true, 'can_write' => true]
            );
        }

        $this->command->info("Superadmin ready — {$email}");
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

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
     * Credentials are read from .env (SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD)
     * or fall back to safe defaults suitable only for local dev.
     */
    private function createSuperAdmin(): void
    {
        // Ensure the Spatie role exists before assigning it.
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

        // Assign Spatie role (idempotent — syncRoles replaces any previous role).
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

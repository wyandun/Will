<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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
        $this->seedRoles();
        $this->createSuperAdmin();
        $this->call(FeedSeeder::class);
    }

    private function seedRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::SYSTEM_ADMIN_READONLY,
            Role::ADMIN_SM,
            Role::SB_OWNER,
            Role::SB_EMPLOYEE,
            Role::BB_EMPLOYEE,
            Role::SUB_FRANCHISE_OWNER,
            Role::SUB_FRANCHISE_ADMIN,
        ];

        foreach ($roles as $role) {
            SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function createSuperAdmin(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'admin@smportal.com');
        $password = env('SUPERADMIN_PASSWORD', 'password');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
            ]
        );

        $user->syncRoles([Role::SUPERADMIN]);

        foreach (self::ALL_MODULES as $module) {
            UserPermission::updateOrCreate(
                ['user_id' => $user->id, 'module' => $module],
                ['can_read' => true, 'can_write' => true]
            );
        }

        $this->command->info("Superadmin ready — {$email}");
    }
}

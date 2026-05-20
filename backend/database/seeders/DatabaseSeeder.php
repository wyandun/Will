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

        // withTrashed() ensures a soft-deleted superadmin row is found and
        // restored rather than silently creating a second row with a new id.
        // A drifted superadmin id would invalidate all existing Sanctum tokens
        // and corrupt inviter_id FK references on previously invited users.
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user) {
            $user->restore();
            $user->fill([
                'name' => 'Super Admin',
                'password' => Hash::make($password),
            ]);
        } else {
            $user = new User([
                'name' => 'Super Admin',
                'email' => $email,
                'password' => Hash::make($password),
            ]);
        }

        // Ensure the superadmin is never treated as a pending invitation.
        // Without this, scopePendingInvitation (whereNull invitation_accepted_at)
        // would include the superadmin record in the pending invitations list
        // if invitation_token were ever accidentally set on this row.
        $user->invitation_token = null;
        $user->invitation_accepted_at = $user->invitation_accepted_at ?? now();
        $user->save();

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

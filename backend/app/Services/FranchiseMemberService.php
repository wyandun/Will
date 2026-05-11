<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class FranchiseMemberService
{
    /**
     * All permission modules available on the platform.
     */
    private const ALL_MODULES = [
        'feed', 'contracts', 'repository', 'processes', 'accounting',
        'inventory', 'tracking', 'catalog', 'calendar', 'applications',
    ];

    /**
     * Modules granted (read + write) per area.
     * null means all modules (full_access).
     *
     * @var array<string, list<string>|null>
     */
    private const AREA_MODULES = [
        'full_access' => null,
        'accounting' => ['accounting'],
        'marketing' => ['feed', 'calendar'],
        'operations' => ['inventory', 'tracking', 'processes'],
        'legal' => ['contracts', 'repository'],
        'human_resources' => ['feed'],
    ];

    // ---------------------------------------------------------------------------
    // Queries
    // ---------------------------------------------------------------------------

    /**
     * Return all admin_sm users and sb_owner/bb_employee users for a franchise.
     *
     * @return array{ admins: Collection, clients: Collection }
     */
    public function getMembers(Franchise $franchise): array
    {
        $admins = User::where('sm_franchise_id', $franchise->id)
            ->role(Role::ADMIN_SM)
            ->with('roles:id,name')
            ->select(['id', 'name', 'email', 'phone', 'job_title', 'area', 'created_at'])
            ->orderBy('name')
            ->get();

        $clients = User::where('sm_franchise_id', $franchise->id)
            ->role([Role::SB_OWNER, Role::BB_EMPLOYEE])
            ->with('roles:id,name')
            ->select(['id', 'name', 'email', 'phone', 'job_title', 'created_at'])
            ->orderBy('name')
            ->get()
            ->map(function (User $u) {
                // Flatten the first role name for easy frontend consumption.
                $u->setAttribute('role', $u->roles->first()?->name);

                return $u;
            });

        return compact('admins', 'clients');
    }

    // ---------------------------------------------------------------------------
    // Mutations
    // ---------------------------------------------------------------------------

    /**
     * Create a new admin_sm user for a franchise.
     * The user is immediately activated (no invitation flow).
     *
     * @param  array<string, mixed>  $data
     */
    public function createAdmin(Franchise $franchise, array $data, User $inviter): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['position'] ?? null,
            'area' => $data['area'],
            'sm_franchise_id' => $franchise->id,
            'inviter_id' => $inviter->id,
            'invitation_accepted_at' => now(),
        ]);

        $user->assignRole(Role::ADMIN_SM);

        $this->assignAreaPermissions($user, $data['area']);

        Log::info('Franchise admin created', [
            'franchise_id' => $franchise->id,
            'user_id' => $user->id,
            'area' => $data['area'],
        ]);

        return $user->load('roles');
    }

    /**
     * Create a new client user (sb_owner or bb_employee) for a franchise.
     * client_type: 'owner' → sb_owner | 'investor' → bb_employee
     *
     * @param  array<string, mixed>  $data
     */
    public function createClient(Franchise $franchise, array $data, User $inviter): User
    {
        $role = $data['client_type'] === 'investor' ? Role::BB_EMPLOYEE : Role::SB_OWNER;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['position'] ?? null,
            'sm_franchise_id' => $franchise->id,
            'inviter_id' => $inviter->id,
            'invitation_accepted_at' => now(),
        ]);

        $user->assignRole($role);

        Log::info('Franchise client created', [
            'franchise_id' => $franchise->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user->load('roles');
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Grant read+write permissions on the modules defined for the given area.
     * full_access grants all modules.
     */
    private function assignAreaPermissions(User $user, string $area): void
    {
        $modules = self::AREA_MODULES[$area] ?? null;
        $granted = $modules ?? self::ALL_MODULES;

        foreach ($granted as $module) {
            UserPermission::create([
                'user_id' => $user->id,
                'module' => $module,
                'can_read' => true,
                'can_write' => true,
            ]);
        }
    }
}

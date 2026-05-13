<?php

namespace App\Services;

use App\Enums\Area;
use App\Enums\ClientType;
use App\Enums\Module;
use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class FranchiseMemberService
{
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
            ->get();

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
        return DB::transaction(function () use ($franchise, $data, $inviter): User {
            $user = $this->buildUser($franchise, $data, $inviter);
            $user->area = $data['area'];
            $user->save();

            $user->assignRole(Role::ADMIN_SM);

            $this->assignAreaPermissions($user, $data['area']);

            Log::info('Franchise admin created', [
                'franchise_id' => $franchise->id,
                'user_id' => $user->id,
                'area' => $data['area'],
            ]);

            return $user->load('roles');
        });
    }

    /**
     * Create a new client user (sb_owner or bb_employee) for a franchise.
     *
     * @param  array<string, mixed>  $data
     */
    public function createClient(Franchise $franchise, array $data, User $inviter): User
    {
        return DB::transaction(function () use ($franchise, $data, $inviter): User {
            $clientType = ClientType::from($data['client_type']);

            $user = $this->buildUser($franchise, $data, $inviter);
            $user->save();

            $user->assignRole($clientType->role());

            Log::info('Franchise client created', [
                'franchise_id' => $franchise->id,
                'user_id' => $user->id,
                'role' => $clientType->role(),
            ]);

            return $user->load('roles');
        });
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function buildUser(Franchise $franchise, array $data, User $inviter): User
    {
        return new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['position'] ?? null,
            'sm_franchise_id' => $franchise->id,
            'inviter_id' => $inviter->id,
            'invitation_accepted_at' => now(),
        ]);
    }

    /**
     * Grant read+write permissions on the modules defined for the given area.
     * full_access grants all modules.
     */
    private function assignAreaPermissions(User $user, string $area): void
    {
        $modules = self::AREA_MODULES[$area] ?? null;
        $granted = $modules ?? Module::values();

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

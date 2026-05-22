<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;

class FranchiseMemberService
{
    /**
     * Return both admins and clients for the given franchise.
     *
     * @return array<string, mixed>
     */
    public function listMembers(Franchise $franchise): array
    {
        abort_if(! $franchise->exists || ! $franchise->getKey(), 500, 'listMembers() requires a persisted Franchise model.');

        $admins = User::where('sm_franchise_id', $franchise->id)
            ->role(Role::ADMIN_SM)
            ->with('userPermissions:id,user_id,module,can_read,can_write')
            ->get(['id', 'name', 'email', 'phone', 'job_title', 'area', 'avatar_path', 'last_seen_at', 'invitation_accepted_at', 'created_at']);

        $deactivatedAdmins = User::withTrashed()
            ->where('sm_franchise_id', $franchise->id)
            ->whereNotNull('deleted_at')
            ->role(Role::ADMIN_SM)
            ->with('userPermissions:id,user_id,module,can_read,can_write')
            ->get(['id', 'name', 'email', 'phone', 'job_title', 'area', 'avatar_path', 'last_seen_at', 'invitation_accepted_at', 'created_at', 'deleted_at']);

        $clients = User::where('sm_franchise_id', $franchise->id)
            ->role([Role::SB_OWNER, Role::BB_EMPLOYEE])
            ->with('roles:name')
            ->get(['id', 'name', 'email', 'phone', 'job_title', 'avatar_path', 'last_seen_at', 'invitation_accepted_at', 'created_at'])
            ->each(function ($client) {
                $client->setAttribute('role', $client->getRoleNames()->first());
                $client->unsetRelation('roles');
            });

        return [
            'franchise_id' => $franchise->id,
            'franchise_name' => $franchise->name,
            'country' => $franchise->country,
            'is_active' => $franchise->is_active,
            'admins_count' => $admins->count(),
            'clients_count' => $clients->count(),
            'admins' => $admins,
            'deactivated_admins' => $deactivatedAdmins,
            'clients' => $clients,
        ];
    }
}

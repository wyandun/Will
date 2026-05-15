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
        $admins = User::where('sm_franchise_id', $franchise->id)
            ->role(Role::ADMIN_SM)
            ->with('userPermissions')
            ->get(['id', 'name', 'email', 'phone', 'job_title', 'area', 'avatar_path', 'last_seen_at', 'invitation_accepted_at']);

        $clients = User::where('sm_franchise_id', $franchise->id)
            ->role([Role::SB_OWNER, Role::BB_EMPLOYEE])
            ->get(['id', 'name', 'email', 'phone', 'job_title', 'avatar_path', 'last_seen_at', 'invitation_accepted_at']);

        return [
            'franchise_id' => $franchise->id,
            'franchise_name' => $franchise->name,
            'admins_count' => $admins->count(),
            'clients_count' => $clients->count(),
            'admins' => $admins,
            'clients' => $clients,
        ];
    }
}

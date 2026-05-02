<?php

namespace App\Services;

use App\Models\Franchise;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class FranchiseService
{
    /**
     * Return franchises scoped to the authenticated user's role, paginated.
     *
     * - superadmin  → all franchises
     * - admin_sm    → only the franchise they belong to (via sm_franchise_id)
     */
    public function list(User $authUser): LengthAwarePaginator
    {
        $columns = [
            'id', 'name', 'type', 'parent_company_id', 'owner_user_id',
            'address', 'phone', 'email', 'country', 'timezone',
            'is_active', 'created_at', 'updated_at'
        ];

        $query = Franchise::select($columns)
            ->withCount([
                'users as admins_count' => function ($q) {
                    $q->whereHas('roles', function ($r) {
                        $r->where('name', 'admin_sm');
                    });
                },
                'companies as clients_count'
            ]);

        if ($authUser->hasRole('superadmin')) {
            return $query->paginate(25);
        }

        // admin_sm sees only their own franchise.
        return $query->where('id', $authUser->sm_franchise_id)->paginate(25);
    }

    /**
     * Create a new franchise record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Franchise
    {
        $franchise = Franchise::create($data);

        Log::info('Franchise created', [
            'franchise_id' => $franchise->id,
            'name' => $franchise->name,
            'type' => $franchise->type,
        ]);

        return $franchise;
    }

    /**
     * Update an existing franchise with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Franchise $franchise, array $data): Franchise
    {
        $franchise->update($data);

        Log::info('Franchise updated', [
            'franchise_id' => $franchise->id,
            'changes' => array_keys($data),
        ]);

        return $franchise->fresh();
    }

    /**
     * Toggle the is_active status of a franchise.
     */
    public function toggleStatus(Franchise $franchise): Franchise
    {
        $franchise->is_active = !$franchise->is_active;
        $franchise->save();

        Log::info('Franchise status toggled', [
            'franchise_id' => $franchise->id,
            'name' => $franchise->name,
            'is_active' => $franchise->is_active,
        ]);

        return $franchise->fresh();
    }

    /**
     * Permanently delete a franchise record.
     */
    public function delete(Franchise $franchise): void
    {
        $franchiseId = $franchise->id;
        $franchiseName = $franchise->name;

        $franchise->delete();

        Log::info('Franchise deleted', [
            'franchise_id' => $franchiseId,
            'name' => $franchiseName,
        ]);
    }
}
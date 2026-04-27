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
        // Only select the columns that FranchiseResource serializes — avoids
        // loading large text fields (address, etc.) on listing queries.
        $columns = ['id', 'name', 'type', 'parent_company_id', 'owner_user_id', 'region', 'address', 'phone', 'created_at', 'updated_at'];

        if ($authUser->hasRole('superadmin')) {
            return Franchise::select($columns)->paginate(25);
        }

        // admin_sm sees only their own franchise.
        return Franchise::select($columns)
            ->where('id', $authUser->sm_franchise_id)
            ->paginate(25);
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
            'name'         => $franchise->name,
            'type'         => $franchise->type,
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
            'changes'      => array_keys($data),
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
            'name'         => $franchiseName,
        ]);
    }
}

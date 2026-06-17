<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class RepositoryService
{
    /**
     * List repositories scoped by the requesting user's role.
     *
     * - superadmin / system_admin / system_admin_readonly → all repositories
     * - admin_sm → only repositories for companies in their franchise
     *
     * @return Collection<int, Repository>
     */
    public function list(User $user): Collection
    {
        $query = Repository::query()
            ->with(['company.franchise'])
            ->withCount('documents');

        if ($user->hasRole('admin_sm') && ! $user->hasAnyRole(['superadmin', 'system_admin', 'system_admin_readonly'])) {
            $franchiseId = (int) $user->sm_franchise_id;

            $query->whereHas('company', function ($q) use ($franchiseId): void {
                $q->where('sm_franchise_id', $franchiseId);
            });
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Create a company-level repository.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Repository
    {
        $company = Company::findOrFail((int) $data['company_id']);

        $repository = Repository::create([
            'company_id' => $company->id,
            'sub_franchise_id' => $data['sub_franchise_id'] ?? null,
        ]);

        $repository->load(['company.franchise']);
        $repository->loadCount('documents');

        Log::info('Repository created', [
            'repository_id' => $repository->id,
            'company_id' => $repository->company_id,
        ]);

        return $repository;
    }

    /**
     * Hydrate a repository with the relations and counts needed for show().
     */
    public function show(Repository $repository): Repository
    {
        $repository->load(['company.franchise', 'subFranchise']);
        $repository->loadCount('documents');

        return $repository;
    }

    /**
     * Permanently delete a repository and all its documents (cascade via DB).
     */
    public function delete(Repository $repository): void
    {
        $id = $repository->id;
        $companyId = $repository->company_id;

        $repository->delete();

        Log::info('Repository deleted', [
            'repository_id' => $id,
            'company_id' => $companyId,
        ]);
    }
}

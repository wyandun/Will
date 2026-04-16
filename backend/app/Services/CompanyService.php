<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ProcessMap;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyService
{
    /**
     * Return companies scoped to the authenticated user's role, paginated.
     *
     * - superadmin → all companies, franchise name eager-loaded
     * - admin_sm   → only companies belonging to their SM franchise
     */
    public function list(User $authUser): LengthAwarePaginator
    {
        if ($authUser->hasRole('superadmin')) {
            return Company::with('franchise')->paginate(25);
        }

        // admin_sm sees only companies managed by their franchise.
        return Company::with('franchise')
            ->where('sm_franchise_id', $authUser->sm_franchise_id)
            ->paginate(25);
    }

    /**
     * Create a new company record and its two required BPMN process maps
     * inside a single DB transaction.
     *
     * CRITICAL BUSINESS RULE: every company must have exactly two process maps:
     *   - type='franquiciadora'
     *   - type='franquiciada'
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Company
    {
        $company = DB::transaction(function () use ($data): Company {
            $company = Company::create($data);

            ProcessMap::create([
                'company_id' => $company->id,
                'type'       => 'franquiciadora',
                'name_es'    => 'Mapa Franquiciadora',
                'name_en'    => 'Franchisor Map',
            ]);

            ProcessMap::create([
                'company_id' => $company->id,
                'type'       => 'franquiciada',
                'name_es'    => 'Mapa Franquiciada',
                'name_en'    => 'Franchisee Map',
            ]);

            return $company;
        });

        Log::info('Company created', [
            'company_id' => $company->id,
            'name'       => $company->name,
        ]);

        // Load franchise relationship so CompanyResource can serialize franchise_name,
        // consistent with closeDeal() which does the same before returning.
        return $company->load('franchise');
    }

    /**
     * Update an existing company with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data): Company
    {
        $company->update($data);

        Log::info('Company updated', [
            'company_id' => $company->id,
            'changes'    => array_keys($data),
        ]);

        return $company->fresh(['franchise']);
    }

    /**
     * Permanently delete a company record.
     */
    public function delete(Company $company): void
    {
        $companyId   = $company->id;
        $companyName = $company->name;

        $company->delete();

        Log::info('Company deleted', [
            'company_id' => $companyId,
            'name'       => $companyName,
        ]);
    }

    /**
     * "Close Deal" — registers a new company and auto-creates its two
     * required BPMN process maps inside a single DB transaction.
     *
     * CRITICAL BUSINESS RULE: every company must have exactly two process maps:
     *   - type='franquiciadora'
     *   - type='franquiciada'
     *
     * If any step fails the entire transaction rolls back.
     *
     * @param  array<string, mixed>  $data
     */
    public function closeDeal(array $data): Company
    {
        $company = DB::transaction(function () use ($data): Company {
            // Step 1 — create the company record.
            $company = Company::create($data);

            // Step 2 — create the franquiciadora process map.
            ProcessMap::create([
                'company_id' => $company->id,
                'type'       => 'franquiciadora',
                'name_es'    => 'Mapa Franquiciadora',
                'name_en'    => 'Franchisor Map',
            ]);

            // Step 3 — create the franquiciada process map.
            ProcessMap::create([
                'company_id' => $company->id,
                'type'       => 'franquiciada',
                'name_es'    => 'Mapa Franquiciada',
                'name_en'    => 'Franchisee Map',
            ]);

            return $company;
        });

        Log::info('Close deal completed — company and process maps created', [
            'company_id' => $company->id,
            'name'       => $company->name,
        ]);

        return $company->load('franchise');
    }
}

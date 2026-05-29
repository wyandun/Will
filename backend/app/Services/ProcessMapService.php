<?php

namespace App\Services;

use App\Models\ProcessMap;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ProcessMapService
{
    /**
     * List process maps with optional filtering by company and franchise.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = ProcessMap::query()->with(['company.franchise']);

        if (! empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (! empty($filters['franchise_id'])) {
            $query->whereHas('company', function ($q) use ($filters): void {
                $q->where('sm_franchise_id', $filters['franchise_id']);
            });
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 24;

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Create a new process map.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProcessMap
    {
        $map = ProcessMap::create($data);

        Log::info('Process map created', [
            'process_map_id' => $map->id,
            'company_id' => $map->company_id,
            'type' => $map->type,
        ]);

        return $map->load('company.franchise');
    }

    /**
     * Permanently delete a process map.
     */
    public function delete(ProcessMap $map): void
    {
        $id = $map->id;
        $companyId = $map->company_id;

        $map->delete();

        Log::info('Process map deleted', [
            'process_map_id' => $id,
            'company_id' => $companyId,
        ]);
    }
}

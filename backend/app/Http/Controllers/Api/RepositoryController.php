<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Repository\StoreRepositoryRequest;
use App\Http\Resources\ProcessTreeCategoryResource;
use App\Http\Resources\RepositoryResource;
use App\Models\ProcessMap;
use App\Models\Repository;
use App\Services\RepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RepositoryController extends Controller
{
    public function __construct(private RepositoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Repository::class);

        $repositories = $this->service->list($request->user());

        return response()->json([
            'success' => true,
            'data' => RepositoryResource::collection($repositories),
        ]);
    }

    public function store(StoreRepositoryRequest $request): JsonResponse
    {
        $this->authorize('create', [Repository::class, $request->validated('company_id')]);

        $repository = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new RepositoryResource($repository),
            'message' => 'repositories.created_success',
        ], 201);
    }

    public function show(Repository $repository): JsonResponse
    {
        $this->authorize('view', $repository);

        return response()->json([
            'success' => true,
            'data' => new RepositoryResource($this->service->show($repository)),
        ]);
    }

    /**
     * Return the "franquiciadora" process tree of this repository's company with
     * its current process documents nested under each sub-process.
     *
     * Used by the "Process Documents" tab of the corporate document repository.
     */
    public function processDocuments(Repository $repository): JsonResponse
    {
        $this->authorize('view', $repository);

        $map = ProcessMap::query()
            ->where('company_id', $repository->company_id)
            ->where('type', 'franquiciadora')
            ->first();

        if ($map === null) {
            return response()->json(['data' => null]);
        }

        $map->load([
            'categories' => fn ($q) => $q->orderBy('order_index'),
            'categories.processes' => fn ($q) => $q->orderBy('order_index'),
            'categories.processes.subProcesses' => fn ($q) => $q->orderBy('order_index'),
            'categories.processes.subProcesses.documents',
        ]);

        return response()->json([
            'data' => [
                'process_map_id' => $map->id,
                'categories' => ProcessTreeCategoryResource::collection($map->categories),
            ],
        ]);
    }

    public function destroy(Repository $repository): Response
    {
        $this->authorize('delete', $repository);

        $this->service->delete($repository);

        return response()->noContent();
    }
}

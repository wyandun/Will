<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Repository\StoreRepositoryRequest;
use App\Http\Resources\RepositoryResource;
use App\Models\Repository;
use App\Services\RepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RepositoryController extends Controller
{
    public function __construct(private RepositoryService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Repository::class);

        $repositories = $this->service->list(auth()->user());

        return RepositoryResource::collection($repositories);
    }

    public function store(StoreRepositoryRequest $request): JsonResponse
    {
        $this->authorize('create', Repository::class);

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

        $repository->load(['company.franchise', 'subFranchise']);
        $repository->loadCount('documents');

        return response()->json([
            'success' => true,
            'data' => new RepositoryResource($repository),
        ]);
    }

    public function destroy(Repository $repository): JsonResponse
    {
        $this->authorize('delete', $repository);

        $this->service->delete($repository);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'repositories.deleted_success',
        ]);
    }
}

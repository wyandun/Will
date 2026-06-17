<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Repository\StoreRepositoryRequest;
use App\Http\Resources\RepositoryResource;
use App\Models\Repository;
use App\Services\RepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json([
            'success' => true,
            'data' => new RepositoryResource($this->service->show($repository)),
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

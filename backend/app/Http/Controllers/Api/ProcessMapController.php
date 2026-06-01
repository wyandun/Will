<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcessMapRequest;
use App\Http\Resources\ProcessMapResource;
use App\Http\Resources\ProcessMapTreeResource;
use App\Models\ProcessMap;
use App\Services\ProcessMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProcessMapController extends Controller
{
    public function __construct(private ProcessMapService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProcessMap::class);

        $filters = $request->only(['company_id', 'franchise_id', 'per_page']);

        return ProcessMapResource::collection($this->service->list($filters));
    }

    public function store(StoreProcessMapRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Policy::create needs the company id to apply admin_sm franchise-scope.
        $this->authorize('create', [ProcessMap::class, (int) $data['company_id']]);

        $map = $this->service->create($data);

        return response()->json([
            'success' => true,
            'data' => new ProcessMapResource($map),
            'message' => 'process_maps.created_success',
        ], 201);
    }

    public function show(ProcessMap $processMap): JsonResponse
    {
        $this->authorize('view', $processMap);

        $processMap->load([
            'company.franchise',
            'categories' => fn ($q) => $q->orderBy('order_index'),
            'categories.processes' => fn ($q) => $q->orderBy('order_index'),
            'categories.processes.subProcesses' => fn ($q) => $q->orderBy('order_index'),
            'categories.processes.subProcesses.subSubProcesses' => fn ($q) => $q->orderBy('order_index'),
        ]);

        return response()->json(['success' => true, 'data' => new ProcessMapTreeResource($processMap)]);
    }

    public function destroy(ProcessMap $processMap): JsonResponse
    {
        $this->authorize('delete', $processMap);

        $this->service->delete($processMap);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'process_maps.deleted_success',
        ]);
    }
}

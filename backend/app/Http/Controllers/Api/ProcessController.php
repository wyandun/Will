<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcessRequest;
use App\Http\Requests\UpdateProcessRequest;
use App\Http\Resources\ProcessResource;
use App\Models\Process;
use App\Models\ProcessCategory;
use App\Services\ProcessService;
use Illuminate\Http\JsonResponse;

class ProcessController extends Controller
{
    public function __construct(private ProcessService $service) {}

    public function store(StoreProcessRequest $request, ProcessCategory $processCategory): JsonResponse
    {
        $this->authorize('create', [Process::class, $processCategory]);

        $process = $this->service->create($processCategory, $request->validated());

        return response()->json(['success' => true, 'data' => new ProcessResource($process)], 201);
    }

    public function update(UpdateProcessRequest $request, Process $process): JsonResponse
    {
        $this->authorize('update', $process);

        $updated = $this->service->update($process, $request->validated());

        return response()->json(['success' => true, 'data' => new ProcessResource($updated)]);
    }

    public function destroy(Process $process): JsonResponse
    {
        $this->authorize('delete', $process);

        $this->service->delete($process);

        return response()->json(['success' => true, 'data' => null]);
    }
}

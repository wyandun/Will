<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubProcessRequest;
use App\Http\Requests\UpdateSubProcessRequest;
use App\Http\Resources\SubProcessResource;
use App\Models\Process;
use App\Models\SubProcess;
use App\Services\SubProcessService;
use Illuminate\Http\JsonResponse;

class SubProcessController extends Controller
{
    public function __construct(private SubProcessService $service) {}

    public function store(StoreSubProcessRequest $request, Process $process): JsonResponse
    {
        $this->authorize('create', [SubProcess::class, $process]);

        $subProcess = $this->service->create($process, $request->validated());

        return response()->json(['success' => true, 'data' => new SubProcessResource($subProcess)], 201);
    }

    public function update(UpdateSubProcessRequest $request, SubProcess $subProcess): JsonResponse
    {
        $this->authorize('update', $subProcess);

        $updated = $this->service->update($subProcess, $request->validated());

        return response()->json(['success' => true, 'data' => new SubProcessResource($updated)]);
    }

    public function destroy(SubProcess $subProcess): JsonResponse
    {
        $this->authorize('delete', $subProcess);

        $this->service->delete($subProcess);

        return response()->json(['success' => true, 'data' => null]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubSubProcessRequest;
use App\Http\Requests\UpdateSubSubProcessRequest;
use App\Http\Resources\SubSubProcessResource;
use App\Models\SubProcess;
use App\Models\SubSubProcess;
use App\Services\SubSubProcessService;
use Illuminate\Http\JsonResponse;

class SubSubProcessController extends Controller
{
    public function __construct(private SubSubProcessService $service) {}

    public function store(StoreSubSubProcessRequest $request, SubProcess $subProcess): JsonResponse
    {
        $this->authorize('create', [SubSubProcess::class, $subProcess]);

        $subSubProcess = $this->service->create($subProcess, $request->validated());

        return response()->json(['success' => true, 'data' => new SubSubProcessResource($subSubProcess)], 201);
    }

    public function update(UpdateSubSubProcessRequest $request, SubSubProcess $subSubProcess): JsonResponse
    {
        $this->authorize('update', $subSubProcess);

        $updated = $this->service->update($subSubProcess, $request->validated());

        return response()->json(['success' => true, 'data' => new SubSubProcessResource($updated)]);
    }

    public function destroy(SubSubProcess $subSubProcess): JsonResponse
    {
        $this->authorize('delete', $subSubProcess);

        $this->service->delete($subSubProcess);

        return response()->json(['success' => true, 'data' => null]);
    }
}

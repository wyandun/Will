<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProcessCategoryRequest;
use App\Http\Resources\ProcessCategoryResource;
use App\Models\ProcessCategory;
use App\Services\ProcessCategoryService;
use Illuminate\Http\JsonResponse;

class ProcessCategoryController extends Controller
{
    public function __construct(private ProcessCategoryService $service) {}

    public function update(UpdateProcessCategoryRequest $request, ProcessCategory $processCategory): JsonResponse
    {
        $this->authorize('update', $processCategory);

        $category = $this->service->update($processCategory, $request->validated());

        return response()->json(['success' => true, 'data' => new ProcessCategoryResource($category)]);
    }
}

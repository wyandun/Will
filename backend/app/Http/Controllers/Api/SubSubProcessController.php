<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\StoreSubSubProcessRequest;
use App\Http\Requests\UpdateSubSubProcessRequest;
use App\Http\Requests\UploadBpmnRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\SubSubProcessDetailResource;
use App\Http\Resources\SubSubProcessResource;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\ProcessMap;
use App\Models\SubProcess;
use App\Models\SubSubProcess;
use App\Models\User;
use App\Services\BpmnService;
use App\Services\DocumentService;
use App\Services\SubSubProcessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class SubSubProcessController extends Controller
{
    public function __construct(
        private SubSubProcessService $service,
        private BpmnService $bpmnService,
        private DocumentService $documentService,
    ) {}

    public function show(SubSubProcess $subSubProcess): JsonResponse
    {
        $this->authorize('view', $subSubProcess);

        $subSubProcess->load([
            'documents.creator', 'documents.reviewer', 'documents.approver',
            'manualDocument', 'subProcess.process.category.processMap.company',
        ]);

        $franchiseId = $this->resolveFranchiseId($subSubProcess);

        return response()->json([
            'success' => true,
            'data' => new SubSubProcessDetailResource($subSubProcess),
            'reviewers' => $this->franchiseReviewers($franchiseId),
        ]);
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    private function franchiseReviewers(?int $franchiseId): Collection
    {
        if ($franchiseId === null) {
            return collect();
        }

        return User::query()
            ->where('sm_franchise_id', $franchiseId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);
    }

    private function resolveFranchiseId(SubSubProcess $subSubProcess): ?int
    {
        $subProcess = $subSubProcess->subProcess instanceof SubProcess ? $subSubProcess->subProcess : null;
        $process = $subProcess?->process instanceof Process ? $subProcess->process : null;
        $category = $process?->category instanceof ProcessCategory ? $process->category : null;
        $map = $category?->processMap instanceof ProcessMap ? $category->processMap : null;
        $company = $map?->company instanceof Company ? $map->company : null;

        return $company ? (int) $company->sm_franchise_id : null;
    }

    public function uploadBpmn(UploadBpmnRequest $request, SubSubProcess $subSubProcess): JsonResponse
    {
        $this->authorize('update', $subSubProcess);

        $validated = $request->validated();
        $updated = $this->bpmnService->store($subSubProcess, $validated['lang'], $validated['bpmn_xml']);
        $updated->load(['documents', 'manualDocument', 'subProcess.process.category.processMap']);

        return response()->json(['success' => true, 'data' => new SubSubProcessDetailResource($updated)]);
    }

    public function storeDocument(StoreDocumentRequest $request, SubSubProcess $subSubProcess): JsonResponse
    {
        $this->authorize('update', $subSubProcess);

        $document = $this->documentService->create($subSubProcess, $request->validated());

        return response()->json(['success' => true, 'data' => new DocumentResource($document)], 201);
    }

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

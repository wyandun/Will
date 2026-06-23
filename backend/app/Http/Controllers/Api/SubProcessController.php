<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\StoreSubProcessRequest;
use App\Http\Requests\UpdateNodeLinksRequest;
use App\Http\Requests\UpdateSubProcessRequest;
use App\Http\Requests\UploadBpmnRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\SubProcessDetailResource;
use App\Http\Resources\SubProcessResource;
use App\Models\Company;
use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\ProcessMap;
use App\Models\SubProcess;
use App\Models\User;
use App\Services\BpmnService;
use App\Services\DocumentService;
use App\Services\NodeLinkService;
use App\Services\SubProcessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class SubProcessController extends Controller
{
    public function __construct(
        private SubProcessService $service,
        private BpmnService $bpmnService,
        private DocumentService $documentService,
        private NodeLinkService $nodeLinkService,
    ) {}

    public function show(SubProcess $subProcess): JsonResponse
    {
        $this->authorize('view', $subProcess);

        $subProcess->load([
            'documents.creator', 'documents.reviewer', 'documents.approver',
            'manualDocument', 'process.category.processMap.company',
        ]);

        $franchiseId = $this->resolveFranchiseId($subProcess);

        return response()->json([
            'success' => true,
            'data' => new SubProcessDetailResource($subProcess),
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

    private function resolveFranchiseId(SubProcess $subProcess): ?int
    {
        $process = $subProcess->process instanceof Process ? $subProcess->process : null;
        $category = $process?->category instanceof ProcessCategory ? $process->category : null;
        $map = $category?->processMap instanceof ProcessMap ? $category->processMap : null;
        $company = $map?->company instanceof Company ? $map->company : null;

        return $company ? (int) $company->sm_franchise_id : null;
    }

    public function uploadBpmn(UploadBpmnRequest $request, SubProcess $subProcess): JsonResponse
    {
        $this->authorize('update', $subProcess);

        $validated = $request->validated();
        $updated = $this->bpmnService->store($subProcess, $validated['lang'], $validated['bpmn_xml']);
        $updated->load(['documents', 'manualDocument', 'process.category.processMap']);

        return response()->json(['success' => true, 'data' => new SubProcessDetailResource($updated)]);
    }

    public function storeDocument(StoreDocumentRequest $request, SubProcess $subProcess): JsonResponse
    {
        $this->authorize('update', $subProcess);

        $document = $this->documentService->create($subProcess, $request->validated());

        return response()->json(['success' => true, 'data' => new DocumentResource($document)], 201);
    }

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

    public function updateNodeLinks(UpdateNodeLinksRequest $request, SubProcess $subProcess): JsonResponse
    {
        $this->authorize('update', $subProcess);

        $updated = $this->nodeLinkService->update($subProcess, $request->validated()['node_links'] ?? []);
        $updated->load(['documents.creator', 'documents.reviewer', 'documents.approver', 'manualDocument', 'process.category.processMap.company']);

        return response()->json(['success' => true, 'data' => new SubProcessDetailResource($updated)]);
    }

    public function destroy(SubProcess $subProcess): JsonResponse
    {
        $this->authorize('delete', $subProcess);

        $this->service->delete($subProcess);

        return response()->json(['success' => true, 'data' => null]);
    }
}

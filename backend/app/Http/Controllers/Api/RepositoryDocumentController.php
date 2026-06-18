<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Repository\StoreRepositoryDocumentRequest;
use App\Http\Resources\RepositoryDocumentResource;
use App\Models\Repository;
use App\Models\RepositoryDocument;
use App\Services\RepositoryDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RepositoryDocumentController extends Controller
{
    public function __construct(private RepositoryDocumentService $service) {}

    public function index(Repository $repository): AnonymousResourceCollection
    {
        $this->authorize('view', $repository);

        $section = request()->query('section', 'setup');
        $category = request()->query('category');

        $documents = $this->service->listBySection(
            $repository,
            (string) $section,
            $category !== null ? (string) $category : null
        );

        return RepositoryDocumentResource::collection($documents);
    }

    public function store(StoreRepositoryDocumentRequest $request, Repository $repository): JsonResponse
    {
        $this->authorize('update', $repository);

        $document = $this->service->store(
            $repository,
            $request->validated(),
            $request->file('file'),
            auth()->user()
        );

        return response()->json([
            'success' => true,
            'data' => new RepositoryDocumentResource($document),
            'message' => 'repository_documents.uploaded_success',
        ], 201);
    }

    public function destroy(Repository $repository, RepositoryDocument $document): JsonResponse
    {
        $this->authorize('delete', $repository);

        abort_if(
            $document->repository_id !== $repository->id,
            404
        );

        $this->service->delete($document);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'repository_documents.deleted_success',
        ]);
    }
}

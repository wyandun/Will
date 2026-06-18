<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Repository\StoreRepositoryDocumentRequest;
use App\Http\Resources\RepositoryDocumentResource;
use App\Models\Repository;
use App\Models\RepositoryDocument;
use App\Services\RepositoryDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RepositoryDocumentController extends Controller
{
    public function __construct(private RepositoryDocumentService $service) {}

    public function index(Request $request, Repository $repository): AnonymousResourceCollection
    {
        $this->authorize('view', $repository);

        $section = DocumentSection::tryFrom(
            (string) $request->query('section', DocumentSection::SETUP->value)
        );

        abort_if($section === null, 422, 'Invalid section value.');

        $category = $request->query('category');

        $documents = $this->service->listBySection(
            $repository,
            $section->value,
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
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new RepositoryDocumentResource($document),
            'message' => 'repository_documents.uploaded_success',
        ], 201);
    }

    public function destroy(Repository $repository, RepositoryDocument $document): Response
    {
        $this->authorize('delete', $repository);

        abort_if(
            $document->repository_id !== $repository->id,
            404
        );

        $this->service->delete($document);

        return response()->noContent();
    }
}

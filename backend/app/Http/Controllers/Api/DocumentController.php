<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $service) {}

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        $this->authorizeOwner($document);

        $updated = $this->service->update($document, $request->validated());
        $updated->load(['creator', 'reviewer', 'approver']);

        return response()->json(['success' => true, 'data' => new DocumentResource($updated)]);
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->authorizeOwner($document);

        $this->service->delete($document);

        return response()->json(['success' => true, 'data' => null]);
    }

    private function authorizeOwner(Document $document): void
    {
        $owner = $document->documentable;

        if ($owner === null) {
            throw new AccessDeniedHttpException;
        }

        $this->authorize('update', $owner);
    }
}

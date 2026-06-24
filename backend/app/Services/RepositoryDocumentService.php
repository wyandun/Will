<?php

namespace App\Services;

use App\Enums\UploaderRole;
use App\Models\Repository;
use App\Models\RepositoryDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RepositoryDocumentService
{
    /**
     * List current-version documents for a repository section.
     * Optionally scoped to a single setup_category and/or process_code.
     *
     * @return Collection<int, RepositoryDocument>
     */
    public function listBySection(Repository $repository, string $section = 'setup', ?string $category = null, ?string $processCode = null): Collection
    {
        $query = RepositoryDocument::query()
            ->with('uploader')
            ->where('repository_id', $repository->id)
            ->where('section', $section)
            ->where('is_current', true)
            ->orderByDesc('created_at');

        if ($category !== null) {
            $query->where('setup_category', $category);
        }

        if ($processCode !== null) {
            $query->where('process_code', $processCode);
        }

        return $query->get();
    }

    /**
     * Upload and persist a new repository document.
     */
    public function store(Repository $repository, array $data, UploadedFile $file, User $uploader): RepositoryDocument
    {
        $category = $data['setup_category'] ?? null;
        $storagePath = "repositories/{$repository->id}/setup/{$category}";

        $path = $file->store($storagePath, 'public');
        $fileUrl = Storage::disk('public')->url((string) $path);

        $uploaderRole = $uploader->hasAnyRole(['superadmin', 'system_admin', 'admin_sm'])
            ? UploaderRole::SM
            : UploaderRole::CLIENT;

        $document = RepositoryDocument::create([
            'repository_id' => $repository->id,
            'section' => $data['section'],
            'setup_category' => $category,
            'process_code' => $data['process_code'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => (string) $path,
            'file_type' => $file->getMimeType() ?? $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $uploader->id,
            'uploader_role' => $uploaderRole,
        ]);

        // Set non-fillable versioning fields explicitly
        $document->file_url = $fileUrl;
        $document->version = 1;
        $document->is_current = true;
        $document->save();

        $document->load('uploader');

        Log::info('Repository document uploaded', [
            'document_id' => $document->id,
            'repository_id' => $repository->id,
            'category' => $category,
            'uploader_id' => $uploader->id,
        ]);

        return $document;
    }

    /**
     * Soft-delete a document and remove its stored file.
     */
    public function delete(RepositoryDocument $document): void
    {
        $id = $document->id;
        $repositoryId = $document->repository_id;
        $filePath = $document->file_path;

        $document->delete();

        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        Log::info('Repository document deleted', [
            'document_id' => $id,
            'repository_id' => $repositoryId,
        ]);
    }
}

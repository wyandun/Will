<?php

namespace App\Services;

use App\Models\Document;
use App\Models\SubProcess;
use App\Models\SubSubProcess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Creates, updates and removes process documents.
 *
 * Files (ES / EN) are uploaded to the public disk; metadata includes reviewer /
 * approver (franchise users), valid_from and notes. Codes are auto-generated as
 * {OWNER_CODE}-{TYPE}-NN. A type 'MN' (Manual) — or is_manual — becomes the
 * owner's manual_document_id shortcut used by the "Ver Manual" button.
 */
class DocumentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(SubProcess|SubSubProcess $model, array $data): Document
    {
        $type = $data['type'];
        $sequence = $model->documents()->withTrashed()->where('type', $type)->count() + 1;
        $code = $model->code.'-'.$type.'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

        $attributes = [
            'code' => $code,
            'type' => $type,
            'title_es' => $data['title_es'],
            'title_en' => $data['title_en'],
            'version' => 1,
            'is_current' => true,
            'uploaded_by' => auth()->id(),
            'reviewed_by' => $data['reviewed_by'] ?? null,
            'approved_by' => $data['approved_by'] ?? null,
            'reviewed_at' => ! empty($data['reviewed_by']) ? now() : null,
            'approved_at' => ! empty($data['approved_by']) ? now() : null,
            'valid_from' => $data['valid_from'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        $attributes = array_merge($attributes, $this->fileAttributes($data));

        /** @var Document $document */
        $document = $model->documents()->create($attributes);

        if (($data['is_manual'] ?? false) || $type === 'MN') {
            $model->update(['manual_document_id' => $document->id]);
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Document $document, array $data): Document
    {
        $attributes = array_filter([
            'type' => $data['type'] ?? null,
            'title_es' => $data['title_es'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'valid_from' => $data['valid_from'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null);

        // Reviewer / approver are nullable: presence of the key drives the change.
        if (array_key_exists('reviewed_by', $data)) {
            $attributes['reviewed_by'] = $data['reviewed_by'];
            $attributes['reviewed_at'] = $data['reviewed_by'] ? now() : null;
        }
        if (array_key_exists('approved_by', $data)) {
            $attributes['approved_by'] = $data['approved_by'];
            $attributes['approved_at'] = $data['approved_by'] ? now() : null;
        }

        $attributes = array_merge($attributes, $this->fileAttributes($data));

        $document->update($attributes);

        return $document->fresh() ?? $document;
    }

    public function delete(Document $document): void
    {
        $document->delete();
    }

    /**
     * Store any provided ES/EN files and return the matching column attributes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function fileAttributes(array $data): array
    {
        $attributes = [];

        if (($data['file_es'] ?? null) instanceof UploadedFile) {
            $file = $data['file_es'];
            $path = $file->store('process-documents', 'public');
            $attributes['file_url'] = Storage::disk('public')->url($path);
            $attributes['file_name'] = $file->getClientOriginalName();
        }

        if (($data['file_en'] ?? null) instanceof UploadedFile) {
            $file = $data['file_en'];
            $path = $file->store('process-documents', 'public');
            $attributes['file_url_en'] = Storage::disk('public')->url($path);
            $attributes['file_name_en'] = $file->getClientOriginalName();
        }

        return $attributes;
    }
}

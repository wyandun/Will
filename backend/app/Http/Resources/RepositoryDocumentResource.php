<?php

namespace App\Http\Resources;

use App\Models\RepositoryDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RepositoryDocument */
class RepositoryDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repository_id' => $this->repository_id,
            'section' => $this->section,
            'setup_category' => $this->setup_category,
            'title' => $this->title,
            'description' => $this->description,
            'file_url' => $this->file_url,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'uploader_role' => $this->uploader_role,
            'uploader' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),
            'version' => $this->version,
            'is_current' => $this->is_current,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

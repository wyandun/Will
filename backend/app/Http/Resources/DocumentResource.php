<?php

namespace App\Http\Resources;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'code' => $this->code,
            'title_es' => $this->title_es,
            'title_en' => $this->title_en,
            'version' => $this->version,
            'is_current' => (bool) $this->is_current,
            'file_url' => $this->file_url,
            'file_name' => $this->file_name,
            'file_url_en' => $this->file_url_en,
            'file_name_en' => $this->file_name_en,
            'valid_from' => $this->valid_from?->toDateString(),
            'notes' => $this->notes,
            'created_by' => $this->userRef($this->creator),
            'created_at' => $this->created_at?->toDateString(),
            'reviewed_by' => $this->userRef($this->reviewer),
            'reviewed_at' => $this->reviewed_at?->toDateString(),
            'approved_by' => $this->userRef($this->approver),
            'approved_at' => $this->approved_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userRef(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }
}

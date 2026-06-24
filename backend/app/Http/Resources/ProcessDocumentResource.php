<?php

namespace App\Http\Resources;

use App\Models\ProcessDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProcessDocument */
class ProcessDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'title_es' => $this->title_es,
            'title_en' => $this->title_en,
            'file_url' => $this->file_url,
            'version' => $this->version,
        ];
    }
}

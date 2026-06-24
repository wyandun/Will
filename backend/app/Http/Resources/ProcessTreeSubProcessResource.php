<?php

namespace App\Http\Resources;

use App\Models\SubProcess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SubProcess */
class ProcessTreeSubProcessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_es' => $this->name_es,
            'name_en' => $this->name_en,
            'documents' => ProcessDocumentResource::collection($this->currentDocuments),
        ];
    }
}

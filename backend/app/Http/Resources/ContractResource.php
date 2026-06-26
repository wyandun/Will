<?php

namespace App\Http\Resources;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Contract */
class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'sm_franchise_id' => $this->company->sm_franchise_id,
                'franchise' => $this->company->relationLoaded('franchise') && $this->company->franchise
                    ? [
                        'id' => $this->company->franchise->id,
                        'name' => $this->company->franchise->name,
                    ]
                    : null,
            ]),
            'client_user_id' => $this->client_user_id,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
            ] : null),
            'title' => $this->title,
            'description' => $this->description,
            'draft_url' => $this->draft_url,
            'status' => $this->status,
            'docuseal_template_id' => $this->docuseal_template_id,
            'docuseal_submission_id' => $this->docuseal_submission_id,
            'signed_document_url' => $this->signed_document_url,
            'certificate_url' => $this->certificate_url,
            'signers' => $this->signers,
            'sent_at' => $this->sent_at?->toISOString(),
            'signed_at' => $this->signed_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

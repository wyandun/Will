<?php

namespace App\Http\Resources;

use App\Models\AssessmentContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssessmentContact */
class AssessmentContactResource extends JsonResource
{
    /**
     * Transform the assessment contact model into an API-ready array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'current_stage' => $this->current_stage,
            'company_name' => $this->company_name,
            'company_industry' => $this->company_industry,
            'company_phone' => $this->company_phone,
            'company_email' => $this->company_email,
            'company_address' => $this->company_address,
            'company_state' => $this->company_state,
            'company_zip' => $this->company_zip,
            'years_operating' => $this->years_operating,
            'employees_count' => $this->employees_count,
            'annual_revenue' => $this->annual_revenue,
            'contact_name' => $this->contact_name,
            'contact_title' => $this->contact_title,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'preferred_lang' => $this->preferred_lang,
            'best_time' => $this->best_time,
            'score' => $this->score,
            'score_breakdown' => $this->score_breakdown,
            'result_pdf_path' => $this->result_pdf_path,
            // Public notes filled by the contact themselves
            'notes' => $this->notes,
            // Internal audit annotation — only visible to admin_sm / superadmin
            'admin_note' => $this->admin_note,
            'admin_noted_at' => $this->admin_noted_at?->toISOString(),
            'admin_noted_by' => $this->whenLoaded(
                'adminNotedByUser',
                fn () => [
                    'id' => $this->adminNotedByUser->id,
                    'name' => $this->adminNotedByUser->name,
                ]
            ),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'converted_company_id' => $this->converted_company_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

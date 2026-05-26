<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    /**
     * Transform the event model into an API-ready array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            // Dates are returned without timezone offset (no "Z" suffix) so the frontend
            // displays the exact time the user entered. The stored timezone is available
            // separately in the "timezone" field for reference purposes.
            'start_at' => $this->start_at->format('Y-m-d\TH:i:s'),
            'end_at' => $this->end_at->format('Y-m-d\TH:i:s'),
            'all_day' => $this->all_day,
            'timezone' => $this->timezone,
            'color' => $this->color,
            'visibility' => $this->visibility,
            'type' => $this->type,
            'rrule' => $this->rrule,
            'reminder_minutes' => $this->reminder_minutes,
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'attendees' => $this->whenLoaded('attendees', fn () => $this->attendees->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
                'rsvp_status' => $u->getAttribute('pivot')?->rsvp_status,
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

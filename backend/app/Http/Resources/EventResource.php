<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\User|null $relatedUser */
        $relatedUser = $this->resource->user;

        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'location'    => $this->location,
            'start_at'    => $this->start_at->toIso8601String(),
            'end_at'      => $this->end_at->toIso8601String(),
            'timezone'    => $this->timezone,
            'all_day'     => (bool) $this->all_day,
            'color'       => $this->color,
            'visibility'  => $this->visibility,
            'type'        => $this->type,
            'user'        => [
                'id'   => $relatedUser?->id,
                'name' => $relatedUser?->name,
            ],
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}

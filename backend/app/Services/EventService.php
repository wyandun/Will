<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EventService
{
    /**
     * List events with optional filters, paginated.
     *
     * @param  array<string, mixed>  $filters  Supports: search, start_from, end_before
     */
    public function list(User $user, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Event::with(['creator', 'attendees']);

        // Superadmin and system admins see all events regardless of visibility
        if (! $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])) {
            $query->where(function ($q) use ($user) {
                $q->where('visibility', 'public')
                    ->orWhere('user_id', $user->id);

                if ($user->sm_franchise_id !== null) {
                    $q->orWhere(function ($fq) use ($user) {
                        $fq->where('visibility', 'franchise')
                            ->whereHas('creator', function ($uq) use ($user) {
                                $uq->where('sm_franchise_id', $user->sm_franchise_id);
                            });
                    });
                }
            });
        }

        $search = $filters['search'] ?? null;
        if ($search) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $query->where(function ($q) use ($escaped) {
                $q->where('title', 'ilike', "%{$escaped}%")
                    ->orWhere('description', 'ilike', "%{$escaped}%")
                    ->orWhere('location', 'ilike', "%{$escaped}%");
            });
        }

        if (! empty($filters['start_from'])) {
            $query->where('end_at', '>=', Carbon::parse($filters['start_from'])->startOfDay());
        }

        if (! empty($filters['end_before'])) {
            $query->where('start_at', '<=', Carbon::parse($filters['end_before'])->endOfDay());
        }

        return $query->orderBy('start_at')->paginate($perPage);
    }

    /**
     * Create a new event.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Event
    {
        $data['user_id'] = $user->id;
        $data['visibility'] = $data['visibility'] ?? 'private';
        $data['type'] = $data['type'] ?? 'casual';

        $this->normalizeAllDay($data);

        $attendeeIds = $data['attendee_ids'] ?? null;
        unset($data['attendee_ids']);

        $event = DB::transaction(function () use ($data, $attendeeIds) {
            $event = Event::create($data);

            if (is_array($attendeeIds)) {
                $event->attendees()->sync($attendeeIds);
            }

            return $event;
        });

        Log::info('Event created', ['event_id' => $event->id, 'user_id' => $user->id]);

        return $event->load(['creator', 'attendees']);
    }

    /**
     * Update an existing event.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data): Event
    {
        if (isset($data['end_at']) && ! isset($data['start_at'])) {
            if (Carbon::parse($data['end_at'])->lt($event->start_at)) {
                throw ValidationException::withMessages([
                    'end_at' => ['The end date must be after or equal to the start date.'],
                ]);
            }
        }

        $this->normalizeAllDay($data);

        $attendeeIds = array_key_exists('attendee_ids', $data) ? $data['attendee_ids'] : null;
        $attendeesProvided = array_key_exists('attendee_ids', $data);
        unset($data['attendee_ids']);

        DB::transaction(function () use ($event, $data, $attendeeIds, $attendeesProvided) {
            $event->update($data);

            if ($attendeesProvided) {
                $event->attendees()->sync($attendeeIds ?? []);
            }
        });

        Log::info('Event updated', ['event_id' => $event->id]);

        return $event->load(['creator', 'attendees']);
    }

    /**
     * Soft-delete an event.
     */
    public function delete(Event $event): void
    {
        $event->delete();

        Log::info('Event deleted', ['event_id' => $event->id]);
    }

    /**
     * If all_day is true, normalize start_at and end_at to midnight (00:00:00).
     *
     * @param  array<string, mixed>  $data
     */
    private function normalizeAllDay(array &$data): void
    {
        if (! empty($data['all_day'])) {
            if (isset($data['start_at'])) {
                $data['start_at'] = Carbon::parse($data['start_at'])->startOfDay();
            }
            if (isset($data['end_at'])) {
                $data['end_at'] = Carbon::parse($data['end_at'])->startOfDay();
            }
        }
    }
}

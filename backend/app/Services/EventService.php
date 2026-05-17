<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class EventService
{
    /**
     * List events with optional search, paginated.
     */
    public function list(User $user, ?string $search, int $perPage = 10): LengthAwarePaginator
    {
        $query = Event::with('creator');

        // Superadmin sees all events regardless of visibility
        if (! $user->hasRole(Role::SUPERADMIN)) {
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

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('location', 'ilike', "%{$search}%");
            });
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
        $data['visibility'] = 'public';
        $data['type'] = $data['type'] ?? 'casual';

        $this->normalizeAllDay($data);

        $event = Event::create($data);

        Log::info('Event created', ['event_id' => $event->id, 'user_id' => $user->id]);

        return $event->load('creator');
    }

    /**
     * Update an existing event.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data): Event
    {
        $this->normalizeAllDay($data);

        $event->update($data);

        Log::info('Event updated', ['event_id' => $event->id]);

        return $event->load('creator');
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

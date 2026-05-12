<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EventService
{
    /**
     * Return events visible to the given user.
     *
     * - superadmin / system_admin: all events
     * - admin_sm: own events + franchise-scoped events within their franchise
     * - sb_owner / sb_employee / bb_employee: own events + public events
     * - fallback: own events only
     */
    /**
     * Return events visible to the given user.
     *
     * - superadmin / system_admin: all events
     * - admin_sm: own events + franchise-scoped events within their franchise + public events
     * - sb_owner / sb_employee / bb_employee: own events + public events
     * - fallback: own events only
     *
     * Optional $from / $to constrain results to events whose start_at falls
     * within the supplied range (ISO date strings, both endpoints inclusive).
     */
    public function list(User $user, ?string $from = null, ?string $to = null): Collection
    {
        $role = $user->getRoleNames()->first();

        if (in_array($role, ['superadmin', 'system_admin'])) {
            return Event::with('user')
                ->when($from, fn ($q) => $q->where('start_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('start_at', '<=', $to))
                ->orderBy('start_at')
                ->get();
        }

        if ($role === 'admin_sm' && $user->sm_franchise_id) {
            $franchiseUserIds = User::where('sm_franchise_id', $user->sm_franchise_id)
                ->pluck('id');

            return Event::with('user')
                ->where(function ($q) use ($user, $franchiseUserIds) {
                    $q->where('user_id', $user->id)
                        ->orWhere(function ($q2) use ($franchiseUserIds) {
                            $q2->whereIn('user_id', $franchiseUserIds)
                                ->where('visibility', 'franchise');
                        })
                        ->orWhere('visibility', 'public');
                })
                ->when($from, fn ($q) => $q->where('start_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('start_at', '<=', $to))
                ->orderBy('start_at')
                ->get();
        }

        // sb_owner, sb_employee, bb_employee, sub_franchise_*, and fallback
        return Event::with('user')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('visibility', 'public');
            })
            ->when($from, fn ($q) => $q->where('start_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('start_at', '<=', $to))
            ->orderBy('start_at')
            ->get();
    }

    public function create(User $user, array $data): Event
    {
        return Event::create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'timezone' => $data['timezone'] ?? null,
            'all_day' => $data['all_day'] ?? false,
            'color' => $data['color'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
            'type' => $data['type'] ?? 'casual',
        ]);
    }

    public function update(Event $event, array $data): Event
    {
        $event->update(array_filter([
            'title' => $data['title'] ?? $event->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $event->description,
            'location' => array_key_exists('location', $data) ? $data['location'] : $event->location,
            'start_at' => $data['start_at'] ?? $event->start_at,
            'end_at' => $data['end_at'] ?? $event->end_at,
            'timezone' => array_key_exists('timezone', $data) ? $data['timezone'] : $event->timezone,
            'all_day' => $data['all_day'] ?? $event->all_day,
            'color' => array_key_exists('color', $data) ? $data['color'] : $event->color,
            'visibility' => $data['visibility'] ?? $event->visibility,
            'type' => $data['type'] ?? $event->type,
        ], fn ($v) => $v !== null));

        // Handle explicit nulls for nullable fields
        foreach (['description', 'location', 'timezone', 'color'] as $nullable) {
            if (array_key_exists($nullable, $data) && $data[$nullable] === null) {
                $event->update([$nullable => null]);
            }
        }

        return $event->fresh();
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }
}

<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property string|null $location
 * @property string $color
 * @property string $visibility private|franchise|public
 * @property bool $all_day
 * @property string $timezone
 * @property string $type casual|meeting|deadline|reminder|training
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'location',
        'start_at',
        'end_at',
        'all_day',
        'timezone',
        'color',
        'visibility',
        'type',
        'rrule',
        'reminder_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The user who created this event.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Guests invited to this event (event_attendees pivot).
     *
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_attendees')
            ->withPivot('rsvp_status')
            ->withTimestamps();
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    /**
     * Restrict to events whose end_at is in the future, ordered by start_at ascending.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('end_at', '>=', now())->orderBy('start_at');
    }
}

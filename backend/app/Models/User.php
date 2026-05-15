<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int|null $sm_franchise_id
 * @property int|null $company_id
 * @property int|null $sub_franchise_id
 * @property int|null $inviter_id
 * @property string|null $avatar_path
 * @property Carbon|null $birth_date
 * @property Carbon|null $invitation_accepted_at
 * @property Carbon|null $invitation_expires_at
 * @property Carbon|null $email_sent_at
 * @property Carbon|null $last_seen_at
 * @property-read string|null $avatar_url
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'job_title',
        'bio',
        'birth_date',
        'avatar_path',
        'sm_franchise_id',
        'company_id',
        'sub_franchise_id',
        'area',
        // invitation_token and inviter_id are intentionally NOT fillable (security-sensitive).
        // Set them exclusively via explicit assignment: $user->invitation_token = $value.
        'invitation_accepted_at',
        'invitation_expires_at',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'invitation_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'invitation_accepted_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'sm_franchise_id' => 'integer',
            'company_id' => 'integer',
            'sub_franchise_id' => 'integer',
        ];
    }

    // ---------------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------------

    /**
     * Return the public URL for the user's avatar, or null if none is set.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->avatar_path);
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * Module-level permissions for this user (user_permissions table).
     * Each row represents one module with can_read / can_write flags.
     *
     * @return HasMany<UserPermission, $this>
     */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    /**
     * The admin who sent this user's invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /**
     * Users who have a pending invitation (token set, not yet accepted).
     *
     * Note: Laravel's SoftDeletes global scope automatically appends
     * `deleted_at IS NULL` to this query, preventing soft-deleted
     * invitations from appearing in pending lists.
     */
    public function scopePendingInvitation(Builder $query): Builder
    {
        return $query->whereNotNull('invitation_token')->whereNull('invitation_accepted_at');
    }
}

<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $author_id
 * @property int|null $franchise_id
 * @property string $title
 * @property string $body
 * @property string $type announcement|news|training|alert
 * @property string $visibility global|franchise|company
 * @property bool $is_pinned
 * @property string|null $file_path
 * @property string|null $file_type
 * @property string|null $file_name
 * @property string|null $image_url
 * @property string|null $file_url
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'author_id',
        'franchise_id',
        'title',
        'body',
        'type',
        'visibility',
        'is_pinned',
        'file_path',
        'file_type',
        'file_name',
        'image_url',
        'file_url',
        'scheduled_at',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The user who authored this post.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The SM franchise this post belongs to (null for global posts).
     *
     * @return BelongsTo<Franchise, $this>
     */
    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    /**
     * Likes, comments, and shares on this post.
     *
     * @return HasMany<PostInteraction, $this>
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(PostInteraction::class);
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    /**
     * Restrict to posts that have been published (published_at is null or in the past).
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('published_at')
                ->orWhere('published_at', '<=', now());
        });
    }

    /**
     * Restrict to posts visible to the given user based on their role and scope.
     *
     * - superadmin  : all posts
     * - admin_sm    : global + own franchise
     * - all others  : global + franchise of their company
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('superadmin')) {
            return $query;
        }

        $franchiseId = $user->sm_franchise_id;

        return $query->where(function (Builder $q) use ($franchiseId) {
            $q->where('visibility', 'global')
                ->orWhere(function (Builder $q2) use ($franchiseId) {
                    $q2->where('visibility', 'franchise')
                        ->where('franchise_id', $franchiseId);
                });
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $repository_id
 * @property string $section
 * @property string|null $setup_category
 * @property string|null $category
 * @property string|null $process_code
 * @property string|null $code
 * @property Carbon|null $record_date
 * @property string|null $record_period
 * @property string $title
 * @property string|null $description
 * @property string $file_path
 * @property string|null $file_url
 * @property string $file_type
 * @property int $file_size
 * @property int $uploaded_by
 * @property string $uploaded_by_type role discriminator: 'sm' | 'client' — NOT a morph type column
 * @property int $version
 * @property int|null $parent_id
 * @property bool $is_current
 * @property-read Repository|null $repository
 * @property-read User|null $uploader
 * @property-read RepositoryDocument|null $parent
 */
class RepositoryDocument extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'repository_id',
        'section',
        'setup_category',
        'category',
        'process_code',
        'code',
        'record_date',
        'record_period',
        'title',
        'description',
        'file_path',
        'file_url',
        'file_type',
        'file_size',
        'uploaded_by',
        'uploaded_by_type', // discriminator: 'sm' | 'client' — set by service, not from user input
        'version',
        'parent_id',
        'is_current',
    ];

    /**
     * Attribute casts.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'record_date' => 'date',
        'file_size' => 'integer',
        'version' => 'integer',
        'parent_id' => 'integer',
        'is_current' => 'boolean',
    ];

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    /**
     * Scope to only current (latest) versions of documents.
     *
     * @param  Builder<RepositoryDocument>  $query
     * @return Builder<RepositoryDocument>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The repository this document belongs to.
     *
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repository_id');
    }

    /**
     * The user who uploaded this document.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The previous version of this document, if any.
     *
     * @return BelongsTo<RepositoryDocument, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}

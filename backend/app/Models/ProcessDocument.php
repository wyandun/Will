<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property string $code
 * @property string $type
 * @property string $title_es
 * @property string $title_en
 * @property string|null $description
 * @property string|null $file_url
 * @property int $version
 * @property int|null $parent_id
 * @property bool $is_current
 * @property int|null $uploaded_by
 * @property-read Model $documentable
 * @property-read User|null $uploader
 */
class ProcessDocument extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'code',
        'type',
        'title_es',
        'title_en',
        'description',
        'file_url',
        'version',
        'parent_id',
        'is_current',
        'uploaded_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'version' => 'integer',
        'is_current' => 'boolean',
    ];

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The owning model (Process, SubProcess, or SubSubProcess).
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
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
}

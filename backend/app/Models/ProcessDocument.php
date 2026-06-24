<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Process documents — supporting files attached to any level of the process tree.
 *
 * Polymorphic owner via documentable_type / documentable_id. The morph aliases
 * (process | sub_process | sub_sub_process) are registered in AppServiceProvider.
 *
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
 * @property-read User|null $uploader
 * @property-read ProcessDocument|null $parent
 */
class ProcessDocument extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
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
     * Attribute casts.
     *
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
     * The process-tree node (Process | SubProcess | SubSubProcess) this document belongs to.
     *
     * @return MorphTo<Model, $this>
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

    /**
     * The previous version of this document, if any.
     *
     * @return BelongsTo<ProcessDocument, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}

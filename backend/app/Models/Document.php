<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Process document — a supporting file attached to any level of the process tree
 * (Process / SubProcess / SubSubProcess) via a polymorphic relation.
 *
 * Files (ES = file_url/file_name, EN = file_url_en/file_name_en) are uploaded and
 * stored on the public disk. Type 'MP' backs the "Ver Manual" shortcut.
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
 * @property string|null $file_name
 * @property string|null $file_url_en
 * @property string|null $file_name_en
 * @property int $version
 * @property int|null $parent_id
 * @property bool $is_current
 * @property int|null $uploaded_by
 * @property int|null $reviewed_by
 * @property int|null $approved_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $valid_from
 * @property string|null $notes
 */
class Document extends Model
{
    use SoftDeletes;

    protected $table = 'process_documents';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'code',
        'type',
        'title_es',
        'title_en',
        'description',
        'file_url',
        'file_name',
        'file_url_en',
        'file_name_en',
        'version',
        'parent_id',
        'is_current',
        'uploaded_by',
        'reviewed_by',
        'approved_by',
        'reviewed_at',
        'approved_at',
        'valid_from',
        'notes',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'valid_from' => 'date',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

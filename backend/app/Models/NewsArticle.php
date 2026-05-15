<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $source
 * @property string $article_url
 * @property string $title
 * @property string|null $description
 * @property string|null $image_url
 * @property Carbon|null $published_at
 * @property array|null $keywords_matched
 * @property string|null $ai_summary
 * @property string|null $ai_summary_es
 * @property bool $ai_selected
 * @property string $status pending_ai|pending_review|published|rejected
 * @property int|null $post_id
 * @property Carbon $fetched_at
 */
class NewsArticle extends Model
{
    protected $fillable = [
        'source',
        'article_url',
        'title',
        'description',
        'image_url',
        'published_at',
        'keywords_matched',
        'ai_summary',
        'ai_summary_es',
        'ai_selected',
        'status',
        'post_id',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
            'keywords_matched' => 'array',
            'ai_selected' => 'boolean',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}

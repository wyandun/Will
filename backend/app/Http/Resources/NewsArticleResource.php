<?php

namespace App\Http\Resources;

use App\Models\NewsArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NewsArticle */
class NewsArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'title' => $this->title,
            'title_es' => $this->title_es,
            'article_url' => $this->article_url,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'ai_summary' => $this->ai_summary,
            'ai_summary_es' => $this->ai_summary_es,
            'keywords_matched' => $this->keywords_matched ?? [],
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'fetched_at' => $this->fetched_at->toIso8601String(),
            'post_id' => $this->post_id,
        ];
    }
}

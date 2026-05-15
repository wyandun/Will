<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchNewsJob;
use App\Models\NewsArticle;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NewsController extends Controller
{
    /**
     * List articles ready for superadmin review.
     * Returns pending_review articles (AI-selected, not yet published/rejected).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NewsArticle::class);

        $query = NewsArticle::query()
            ->where('status', 'pending_review')
            ->where('ai_selected', true)
            ->orderByDesc('fetched_at');

        $articles = $query->paginate(20);

        $items = $articles->map(fn (NewsArticle $a) => $this->formatArticle($a));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'current_page' => $articles->currentPage(),
                    'last_page' => $articles->lastPage(),
                    'total' => $articles->total(),
                ],
                'last_fetch_at' => Cache::get('news_last_fetch_at'),
            ],
        ]);
    }

    /**
     * Dispatch FetchNewsJob to queue.
     * Throttled: one dispatch per hour to avoid burning API credits.
     */
    public function fetch(Request $request): JsonResponse
    {
        $this->authorize('fetch', NewsArticle::class);

        // Atomic lock — only one dispatch per hour wins
        if (! Cache::add('news_fetch_lock', true, now()->addHour())) {
            $lastFetch = Cache::get('news_last_fetch_at');

            return response()->json([
                'success' => true,
                'data' => ['queued' => false, 'last_fetch_at' => $lastFetch],
                'message' => 'News was fetched recently. Showing cached results.',
            ]);
        }

        FetchNewsJob::dispatch();

        return response()->json([
            'success' => true,
            'data' => ['queued' => true, 'last_fetch_at' => Cache::get('news_last_fetch_at')],
            'message' => 'News fetch queued. Articles will appear shortly.',
        ]);
    }

    /**
     * Publish an article: create a Feed post with type=news and mark article as published.
     */
    public function publish(Request $request, NewsArticle $newsArticle): JsonResponse
    {
        $this->authorize('publish', $newsArticle);

        if ($newsArticle->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Article already published.',
            ], 422);
        }

        $postData = [
            'author_id' => $request->user()->id,
            'title' => $newsArticle->title,
            'body' => $this->buildPostBody($newsArticle),
            'type' => 'news',
            'visibility' => 'global',
            'is_pinned' => false,
            'published_at' => now(),
        ];

        if ($newsArticle->image_url) {
            $postData['image_url'] = $newsArticle->image_url;
        }

        $post = Post::create($postData);

        $newsArticle->update([
            'status' => 'published',
            'post_id' => $post->id,
        ]);

        $newsArticle->refresh();

        return response()->json([
            'success' => true,
            'data' => ['article' => $this->formatArticle($newsArticle), 'post_id' => $post->id],
            'message' => 'Article published to Feed.',
        ]);
    }

    /**
     * Reject an article — removes it from the review queue.
     */
    public function reject(NewsArticle $newsArticle): JsonResponse
    {
        $this->authorize('reject', $newsArticle);

        $newsArticle->update(['status' => 'rejected']);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Article rejected.',
        ]);
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    private function formatArticle(NewsArticle $article): array
    {
        return [
            'id' => $article->id,
            'source' => $article->source,
            'title' => $article->title,
            'article_url' => $article->article_url,
            'description' => $article->description,
            'image_url' => $article->image_url,
            'ai_summary' => $article->ai_summary,
            'ai_summary_es' => $article->ai_summary_es,
            'keywords_matched' => $article->keywords_matched ?? [],
            'status' => $article->status,
            'published_at' => $article->published_at?->toIso8601String(),
            'fetched_at' => $article->fetched_at->toIso8601String(),
            'post_id' => $article->post_id,
        ];
    }

    private function buildPostBody(NewsArticle $article): string
    {
        $summary = $article->ai_summary ?? $article->description ?? '';
        $url = $article->article_url;
        $source = $article->source;

        return "{$summary}\n\nSource: {$source}\n{$url}";
    }
}

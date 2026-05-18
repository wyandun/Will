<?php

namespace App\Http\Controllers\Api;

use App\Enums\NewsArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\NewsArticleResource;
use App\Jobs\FetchNewsJob;
use App\Models\NewsArticle;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            ->where('status', NewsArticleStatus::PendingReview->value)
            ->where('ai_selected', true)
            ->orderByDesc('fetched_at');

        $articles = $query->paginate(20);

        $items = $articles->map(fn (NewsArticle $a) => NewsArticleResource::make($a)->resolve());

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
     * Lock (5 min TTL) prevents double-dispatch while the job is in flight.
     * The job sets news_last_fetch_at on completion and releases the lock.
     */
    public function fetch(Request $request): JsonResponse
    {
        $this->authorize('fetchAny', NewsArticle::class);

        // Atomic lock — only one dispatch wins while a job is in flight
        if (! Cache::add('news_fetch_lock', true, now()->addMinutes(5))) {
            return response()->json([
                'success' => true,
                'data' => ['queued' => false, 'last_fetch_at' => Cache::get('news_last_fetch_at')],
                'message' => 'News fetch already in progress.',
            ]);
        }

        try {
            FetchNewsJob::dispatch()->onQueue('news');
        } catch (\Throwable $e) {
            Cache::forget('news_fetch_lock');
            throw $e;
        }

        return response()->json([
            'success' => true,
            'data' => ['queued' => true, 'last_fetch_at' => Cache::get('news_last_fetch_at')],
            'message' => 'News fetch queued. Articles will appear shortly.',
        ]);
    }

    /**
     * Publish an article: create a Feed post with type=news and mark article as published.
     * Wrapped in a DB transaction so both writes succeed or neither persists.
     */
    public function publish(Request $request, NewsArticle $newsArticle): JsonResponse
    {
        $this->authorize('publish', $newsArticle);

        if ($newsArticle->status === NewsArticleStatus::Published) {
            return response()->json([
                'success' => false,
                'message' => 'Article already published.',
            ], 422);
        }

        try {
            $post = DB::transaction(function () use ($request, $newsArticle) {
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
                $newsArticle->update(['status' => NewsArticleStatus::Published, 'post_id' => $post->id]);

                return $post;
            });
        } catch (\Throwable $e) {
            Log::error('NewsController::publish failed', [
                'article_id' => $newsArticle->id,
                'article_url' => $newsArticle->article_url,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to publish article: '.$e->getMessage(),
            ], 500);
        }

        $newsArticle->refresh();

        return response()->json([
            'success' => true,
            'data' => ['article' => NewsArticleResource::make($newsArticle)->resolve(), 'post_id' => $newsArticle->post_id],
            'message' => 'Article published to Feed.',
        ]);
    }

    /**
     * Reject an article — removes it from the review queue.
     * Cannot reject an article that is already published or already rejected.
     */
    public function reject(NewsArticle $newsArticle): JsonResponse
    {
        $this->authorize('reject', $newsArticle);

        if (in_array($newsArticle->status, [NewsArticleStatus::Published, NewsArticleStatus::Rejected], true)) {
            return response()->json(['success' => false, 'message' => 'Cannot reject this article.'], 422);
        }

        $newsArticle->update(['status' => NewsArticleStatus::Rejected]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Article rejected.',
        ]);
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    private function buildPostBody(NewsArticle $article): string
    {
        $summary = strip_tags($article->ai_summary ?? $article->description ?? '');
        $url = filter_var($article->article_url, FILTER_VALIDATE_URL) ? $article->article_url : '';
        $source = strip_tags($article->source);

        return "{$summary}\n\nSource: {$source}\n{$url}";
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\NewsArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\NewsArticleResource;
use App\Jobs\FetchNewsJob;
use App\Models\NewsArticle;
use App\Models\Post;
use App\Support\NewsCacheKeys;
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

        $articles = NewsArticle::query()
            ->where('status', NewsArticleStatus::PendingReview)
            ->where('ai_selected', true)
            ->orderByDesc('fetched_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => NewsArticleResource::collection($articles->items())->resolve(),
                'meta' => [
                    'current_page' => $articles->currentPage(),
                    'last_page'    => $articles->lastPage(),
                    'total'        => $articles->total(),
                ],
                'last_fetch_at'     => Cache::get(NewsCacheKeys::LAST_FETCH_AT),
                'last_fetch_result' => Cache::get(NewsCacheKeys::FETCH_RESULT),
                'fetch_in_progress' => Cache::has(NewsCacheKeys::FETCH_LOCK),
            ],
        ]);
    }

    /**
     * Dispatch FetchNewsJob to queue.
     * Cache::add() is the atomic gate — only one request can acquire the lock.
     * If another request already holds it, we return early without dispatching.
     */
    public function fetch(Request $request): JsonResponse
    {
        $this->authorize('fetchAny', NewsArticle::class);

        $lockTtl = (int) config('services.news.fetch_lock_ttl_minutes', 10);

        // Atomic lock acquisition — prevents race conditions from simultaneous requests.
        // If add() returns false the lock was already held; do not dispatch a second job.
        if (! Cache::add(NewsCacheKeys::FETCH_LOCK, true, now()->addMinutes($lockTtl))) {
            return response()->json([
                'success' => true,
                'data'    => ['queued' => false, 'last_fetch_at' => Cache::get(NewsCacheKeys::LAST_FETCH_AT)],
                'message' => 'News fetch already in progress.',
            ]);
        }

        // Lock acquired — dispatch the job. The job will call finalize() which releases
        // the lock on success. On failure, FetchNewsJob::failed() releases it instead.
        FetchNewsJob::dispatch();

        return response()->json([
            'success' => true,
            'data' => [
                'queued'            => true,
                'last_fetch_at'     => Cache::get(NewsCacheKeys::LAST_FETCH_AT),
                'last_fetch_result' => Cache::get(NewsCacheKeys::FETCH_RESULT),
            ],
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

        // URL validation delegated to the model (domain logic lives on the model).
        if (! $newsArticle->hasValidUrl()) {
            return response()->json([
                'success' => false,
                'message' => 'Article has an invalid URL and cannot be published.',
            ], 422);
        }

        try {
            $post = DB::transaction(function () use ($request, $newsArticle) {
                $postData = [
                    'author_id'    => $request->user()->id,
                    'title'        => $newsArticle->title,
                    'body'         => $this->buildPostBody($newsArticle),
                    'type'         => 'news',
                    'visibility'   => 'global',
                    'is_pinned'    => false,
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
                'article_id'  => $newsArticle->id,
                'article_url' => $newsArticle->article_url,
                'user_id'     => $request->user()->id,
                'error'       => $e->getMessage(),
                // Trace is captured automatically by Laravel — do not log getTraceAsString().
            ]);

            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Failed to publish the article. Please try again.',
            ], 500);
        }

        $newsArticle->refresh();

        return response()->json([
            'success' => true,
            'data'    => ['article' => NewsArticleResource::make($newsArticle)->resolve(), 'post_id' => $newsArticle->post_id],
            'message' => 'Article published to Feed.',
        ]);
    }

    /**
     * Reject an article — removes it from the review queue.
     * Cannot reject an article that is already published or already rejected.
     */
    public function reject(Request $request, NewsArticle $newsArticle): JsonResponse
    {
        $this->authorize('reject', $newsArticle);

        if ($newsArticle->status !== NewsArticleStatus::PendingReview) {
            $reason = match ($newsArticle->status) {
                NewsArticleStatus::Published => 'Article is already published.',
                NewsArticleStatus::Rejected  => 'Article is already rejected.',
                default                      => 'Only articles pending review can be rejected.',
            };

            return response()->json(['success' => false, 'message' => $reason], 422);
        }

        $newsArticle->update(['status' => NewsArticleStatus::Rejected]);

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Article rejected.',
        ]);
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    private function buildPostBody(NewsArticle $article): string
    {
        // Post.body is plain text — must never be rendered as raw HTML.
        // React's default text rendering escapes it; do not use dangerouslySetInnerHTML.
        $summary = strip_tags($article->ai_summary ?? $article->description ?? '');
        $url     = $article->article_url;
        $source  = strip_tags($article->source);

        return "{$summary}\n\nSource: {$source}\n{$url}";
    }
}

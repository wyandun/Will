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
                    'last_page' => $articles->lastPage(),
                    'total' => $articles->total(),
                ],
                'last_fetch_at' => Cache::get(NewsCacheKeys::LAST_FETCH_AT),
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
                'data' => ['queued' => false, 'last_fetch_at' => Cache::get(NewsCacheKeys::LAST_FETCH_AT)],
                'message' => 'News fetch already in progress.',
            ]);
        }

        // Lock acquired — dispatch the job. The job will call finalize() which releases
        // the lock on success. On failure, FetchNewsJob::failed() releases it instead.
        FetchNewsJob::dispatch();

        return response()->json([
            'success' => true,
            'data' => [
                'queued' => true,
                'last_fetch_at' => Cache::get(NewsCacheKeys::LAST_FETCH_AT),
                'last_fetch_result' => Cache::get(NewsCacheKeys::FETCH_RESULT),
            ],
            'message' => 'News fetch queued. Articles will appear shortly.',
        ]);
    }

    /**
     * Publish an article: create a Feed post with type=news and mark article as published.
     * Wrapped in a DB transaction so both writes succeed or neither persists.
     * lockForUpdate() inside the transaction prevents double-publish under concurrent requests.
     */
    public function publish(Request $request, NewsArticle $newsArticle): JsonResponse
    {
        $this->authorize('publish', $newsArticle);

        // URL validation delegated to the model (domain logic lives on the model).
        if (! $newsArticle->hasValidUrl()) {
            return response()->json([
                'success' => false,
                'message' => 'Article has an invalid URL and cannot be published.',
            ], 422);
        }

        try {
            $post = DB::transaction(function () use ($request, $newsArticle) {
                // Re-read with a row-level lock so concurrent requests cannot
                // both pass the status guard and create two Posts.
                $locked = NewsArticle::lockForUpdate()->findOrFail($newsArticle->id);

                if (! $locked->status->canBePublished()) {
                    throw new \RuntimeException('already_published');
                }

                // Post type and visibility are plain strings — valid values:
                // type: announcement | news | training | alert
                // visibility: global | franchise | company
                $postData = [
                    'author_id' => $request->user()->id,
                    'title' => $locked->title,
                    'body' => $locked->toPostBody(),
                    'type' => 'news',
                    'visibility' => 'global',
                    'is_pinned' => false,
                    'published_at' => now(),
                ];

                if ($locked->image_url) {
                    $postData['image_url'] = $locked->image_url;
                }

                $post = Post::create($postData);
                $locked->update(['status' => NewsArticleStatus::Published, 'post_id' => $post->id]);

                return $post;
            });
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException && $e->getMessage() === 'already_published') {
                return response()->json([
                    'success' => false,
                    'message' => NewsArticleStatus::Published->transitionErrorMessage('published'),
                ], 422);
            }

            Log::error('NewsController::publish failed', [
                'article_id' => $newsArticle->id,
                'article_url' => $newsArticle->article_url,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to publish the article. Please try again.',
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
    public function reject(Request $request, NewsArticle $newsArticle): JsonResponse
    {
        $this->authorize('reject', $newsArticle);

        if (! $newsArticle->status->canBeRejected()) {
            return response()->json([
                'success' => false,
                'message' => $newsArticle->status->transitionErrorMessage('rejected'),
            ], 422);
        }

        $newsArticle->update(['status' => NewsArticleStatus::Rejected]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Article rejected.',
        ]);
    }
}

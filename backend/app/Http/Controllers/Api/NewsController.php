<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\News\FetchNewsRequest;
use App\Http\Requests\News\ListNewsRequest;
use App\Http\Requests\News\PublishNewsRequest;
use App\Http\Requests\News\RejectNewsRequest;
use App\Http\Resources\NewsArticleResource;
use App\Models\NewsArticle;
use App\Services\NewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NewsController extends Controller
{
    public function __construct(private NewsService $newsService) {}

    /**
     * List articles ready for superadmin review.
     * Returns pending_review articles (AI-selected, not yet published/rejected).
     */
    public function index(ListNewsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', NewsArticle::class);

        $articles = $this->newsService->listForReview(20);
        $meta = $this->newsService->reviewQueueMeta();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => NewsArticleResource::collection($articles->items())->resolve(),
                'meta' => [
                    'current_page' => $articles->currentPage(),
                    'last_page' => $articles->lastPage(),
                    'total' => $articles->total(),
                ],
                'last_fetch_at' => $meta['last_fetch_at'],
                'last_fetch_result' => $meta['last_fetch_result'],
                'fetch_in_progress' => $meta['fetch_in_progress'],
            ],
        ]);
    }

    /**
     * Dispatch FetchNewsJob to queue.
     * Cache::add() inside the service is the atomic gate — only one request can
     * acquire the lock. If another request already holds it, we return early
     * without dispatching.
     */
    public function fetch(FetchNewsRequest $request): JsonResponse
    {
        $this->authorize('fetchAny', NewsArticle::class);

        try {
            $result = $this->newsService->queueFetch();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to queue news fetch. Please try again.',
            ], 500);
        }

        // When the lock was already held we return the short shape (queued/last_fetch_at);
        // on a successful dispatch we also include last_fetch_result, matching the
        // original controller's two distinct response shapes.
        $data = [
            'queued' => $result['queued'],
            'last_fetch_at' => $result['last_fetch_at'],
        ];

        if ($result['queued']) {
            $data['last_fetch_result'] = $result['last_fetch_result'] ?? null;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $result['message'],
        ]);
    }

    /**
     * Publish an article: create a Feed post with type=news and mark article as published.
     */
    public function publish(PublishNewsRequest $request, NewsArticle $newsArticle): JsonResponse
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
            $this->newsService->publish($newsArticle, $request->user());

            $newsArticle->refresh();
        } catch (InvalidStatusTransitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getStatus()->transitionErrorMessage('published'),
            ], 422);
        } catch (\Throwable $e) {
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

        return response()->json([
            'success' => true,
            'data' => ['article' => NewsArticleResource::make($newsArticle)->resolve(), 'post_id' => $newsArticle->post_id],
            'message' => 'Article published to Feed.',
        ]);
    }

    /**
     * Reject an article — removes it from the review queue.
     */
    public function reject(RejectNewsRequest $request, NewsArticle $newsArticle): JsonResponse
    {
        $this->authorize('reject', $newsArticle);

        try {
            $this->newsService->reject($newsArticle);
        } catch (InvalidStatusTransitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getStatus()->transitionErrorMessage('rejected'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Article rejected.',
        ]);
    }
}

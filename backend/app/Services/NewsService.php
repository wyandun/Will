<?php

namespace App\Services;

use App\Enums\NewsArticleStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Jobs\FetchNewsJob;
use App\Models\NewsArticle;
use App\Models\Post;
use App\Models\User;
use App\Support\NewsCacheKeys;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NewsService
{
    /**
     * List articles ready for review (pending_review, AI-selected).
     */
    public function listForReview(int $perPage = 20): LengthAwarePaginator
    {
        return NewsArticle::query()
            ->where('status', NewsArticleStatus::PendingReview)
            ->where('ai_selected', true)
            ->orderByDesc('fetched_at')
            ->paginate($perPage);
    }

    /**
     * Read the cached "review queue" metadata (last fetch timestamps, lock state).
     *
     * @return array{last_fetch_at: mixed, last_fetch_result: mixed, fetch_in_progress: bool}
     */
    public function reviewQueueMeta(): array
    {
        return [
            'last_fetch_at' => Cache::get(NewsCacheKeys::LAST_FETCH_AT),
            'last_fetch_result' => Cache::get(NewsCacheKeys::FETCH_RESULT),
            'fetch_in_progress' => Cache::has(NewsCacheKeys::FETCH_LOCK),
        ];
    }

    /**
     * Atomically acquire the fetch lock and dispatch the news-fetch job.
     *
     * Cache::add() is the atomic gate — only one request can acquire the lock.
     * Returns a payload describing whether the job was queued.
     *
     * @return array{queued: bool, last_fetch_at: mixed, last_fetch_result?: mixed, message: string}
     */
    public function queueFetch(): array
    {
        $lockTtl = (int) config('services.news.fetch_lock_ttl_minutes', 10);

        if (! Cache::add(NewsCacheKeys::FETCH_LOCK, true, now()->addMinutes($lockTtl))) {
            return [
                'queued' => false,
                'last_fetch_at' => Cache::get(NewsCacheKeys::LAST_FETCH_AT),
                'message' => 'News fetch already in progress.',
            ];
        }

        try {
            FetchNewsJob::dispatch()->onQueue('news');
        } catch (\Throwable $e) {
            Cache::forget(NewsCacheKeys::FETCH_LOCK);
            Log::error('NewsService::queueFetch failed to dispatch job', ['error' => $e->getMessage()]);
            throw $e;
        }

        return [
            'queued' => true,
            'last_fetch_at' => Cache::get(NewsCacheKeys::LAST_FETCH_AT),
            'last_fetch_result' => Cache::get(NewsCacheKeys::FETCH_RESULT),
            'message' => 'News fetch queued. Articles will appear shortly.',
        ];
    }

    /**
     * Publish a news article — creates a Feed Post and marks the article published.
     *
     * Wraps both writes in a DB transaction + row-level lock so concurrent
     * requests cannot both pass the status guard and create two Posts.
     */
    public function publish(NewsArticle $article, User $publisher): Post
    {
        $post = null;

        DB::transaction(function () use ($article, $publisher, &$post): void {
            $locked = NewsArticle::lockForUpdate()->findOrFail($article->id);

            if (! $locked->status->canBePublished()) {
                throw new InvalidStatusTransitionException($locked->status, 'published');
            }

            $postData = [
                'author_id' => $publisher->id,
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
        });

        // The transaction succeeded; $post is guaranteed to be assigned.
        /** @var Post $post */
        return $post;
    }

    /**
     * Reject a news article — removes it from the review queue.
     */
    public function reject(NewsArticle $article): void
    {
        DB::transaction(function () use ($article): void {
            $locked = NewsArticle::lockForUpdate()->findOrFail($article->id);

            if (! $locked->status->canBeRejected()) {
                throw new InvalidStatusTransitionException($locked->status, 'rejected');
            }

            $locked->update(['status' => NewsArticleStatus::Rejected]);
        });
    }
}

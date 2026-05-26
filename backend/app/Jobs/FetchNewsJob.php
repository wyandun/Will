<?php

namespace App\Jobs;

use App\Services\AiNewsService;
use App\Services\RssNewsService;
use App\Support\NewsCacheKeys;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchNewsJob implements ShouldQueue
{
    use Queueable;

    public string $queue = 'news';

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(RssNewsService $rss, AiNewsService $ai): void
    {
        // The lock is acquired atomically by NewsController::fetch() before dispatch.
        // The job does not re-acquire the lock — it trusts the controller gate
        // and releases via finalize() on success or failed() on failure.
        Log::info('FetchNewsJob: starting news fetch');

        // Step 1: Fetch new articles from RSS feeds
        $newArticles = $rss->fetchAndFilter();
        Log::info('FetchNewsJob: fetched from RSS', ['new_articles' => count($newArticles)]);

        // Step 2: When no new articles were found from RSS, re-queue old rejected articles
        // so the AI has something to work with. This prevents the pool from drying up
        // when all recent RSS content is already in the database.
        if (count($newArticles) === 0) {
            $requeued = $rss->requeueOldRejected(olderThanDays: 7);
            Log::info('FetchNewsJob: no new RSS articles — re-queued old rejected', ['requeued' => $requeued]);
        }

        // Step 3: Grab up to batch_size pending_ai articles (both new and re-queued)
        $batchSize = (int) config('services.anthropic.batch_size', 30);
        $pending = $rss->getPendingForAi($batchSize);

        if ($pending->isEmpty()) {
            Log::info('FetchNewsJob: no articles pending AI processing — pool is empty');
            Cache::put(NewsCacheKeys::FETCH_RESULT, [
                'new_from_rss' => 0,
                'processed' => 0,
                'message' => 'All recent articles have already been processed. No new content found.',
            ], now()->addHours(12));
            $this->finalize();

            return;
        }

        $ai->processArticles($pending);
        Log::info('FetchNewsJob: AI processing complete', ['processed' => $pending->count()]);

        Cache::put(NewsCacheKeys::FETCH_RESULT, [
            'new_from_rss' => count($newArticles),
            'processed' => $pending->count(),
            'message' => null,
        ], now()->addHours(12));

        $this->finalize();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('FetchNewsJob: job failed', ['error' => $e->getMessage()]);
        Cache::put(NewsCacheKeys::FETCH_RESULT, [
            'new_from_rss' => 0,
            'processed' => 0,
            'message' => 'Fetch failed. Please try again.',
        ], now()->addHours(12));
        $this->finalize();
    }

    private function finalize(): void
    {
        Cache::put(NewsCacheKeys::LAST_FETCH_AT, now()->toIso8601String(), now()->addHours(12));
        Cache::forget(NewsCacheKeys::FETCH_LOCK);
    }
}

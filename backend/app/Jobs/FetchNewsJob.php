<?php

namespace App\Jobs;

use App\Services\AiNewsService;
use App\Services\RssNewsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchNewsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function handle(RssNewsService $rss, AiNewsService $ai): void
    {
        Log::info('FetchNewsJob: starting news fetch');

        $newArticles = $rss->fetchAndFilter();
        Log::info('FetchNewsJob: fetched from RSS', ['new_articles' => count($newArticles)]);

        $pending = $rss->getPendingForAi(15);

        if ($pending->isEmpty()) {
            Log::info('FetchNewsJob: no articles pending AI processing');
            Cache::put('news_last_fetch_at', now()->toIso8601String(), now()->addHours(12));

            return;
        }

        $ai->processArticles($pending);
        Log::info('FetchNewsJob: AI processing complete', ['processed' => $pending->count()]);

        Cache::put('news_last_fetch_at', now()->toIso8601String(), now()->addHours(12));
    }
}

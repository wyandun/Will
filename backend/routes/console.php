<?php

use App\Jobs\FetchNewsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('news:fetch', function () {
    FetchNewsJob::dispatch()->onQueue('news');
    $this->info('FetchNewsJob dispatched to queue.');
})->purpose('Dispatch the news fetch + AI summarization job');

// Run every N hours (default 6, configurable via NEWS_FETCH_INTERVAL_HOURS)
$hours = (int) env('NEWS_FETCH_INTERVAL_HOURS', 6);
Schedule::command('news:fetch')->cron("0 */{$hours} * * *");

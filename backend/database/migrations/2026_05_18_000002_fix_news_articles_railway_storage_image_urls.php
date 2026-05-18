<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clear image_url values that point to the ephemeral Railway/local storage disk.
 *
 * The previous migration only caught "localhost" URLs. In Railway, APP_URL is
 * the Railway domain, so cached images were stored as:
 *   https://will-production-4709.up.railway.app/storage/news/abc123.jpg
 * Those files are destroyed on every deploy. This migration nulls any image_url
 * containing "/storage/news/" regardless of domain, so the next Fetch News
 * can repopulate them with real external RSS URLs.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('news_articles')
            ->where('image_url', 'like', '%/storage/news/%')
            ->update(['image_url' => null]);
    }

    public function down(): void
    {
        // No rollback — the original storage URLs were already broken.
    }
};

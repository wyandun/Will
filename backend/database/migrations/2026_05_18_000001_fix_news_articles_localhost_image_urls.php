<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clear image_url values that point to localhost storage (ephemeral filesystem).
 *
 * Railway deploys destroy local disk on every release, so any image_url that
 * was written as "http://localhost/storage/news/..." is permanently broken.
 * Setting them back to null lets the frontend show a clean placeholder instead
 * of a broken image tag.
 *
 * Going forward, RssNewsService no longer downloads images — it stores the
 * original external RSS image URL directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('news_articles')
            ->where('image_url', 'like', '%localhost%/storage/news/%')
            ->update(['image_url' => null]);
    }

    public function down(): void
    {
        // No rollback — the original localhost URLs were already broken.
    }
};

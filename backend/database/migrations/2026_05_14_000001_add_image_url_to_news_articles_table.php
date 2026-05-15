<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add image_url to news_articles.
     * Stores the first image found in the RSS feed item (media:content,
     * enclosure, or media:thumbnail), used when publishing to the Feed.
     */
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('news_articles', 'image_url')) {
                $table->string('image_url', 500)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            if (Schema::hasColumn('news_articles', 'image_url')) {
                $table->dropColumn('image_url');
            }
        });
    }
};

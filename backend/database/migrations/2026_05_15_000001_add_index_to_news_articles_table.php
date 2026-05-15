<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->index(['status', 'ai_selected', 'fetched_at'], 'news_articles_status_selected_fetched_idx');
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropIndex('news_articles_status_selected_fetched_idx');
        });
    }
};

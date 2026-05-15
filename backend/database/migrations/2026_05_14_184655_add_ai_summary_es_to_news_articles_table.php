<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add ai_summary_es (Spanish AI summary) to news_articles.
     * Also ensures image_url exists in case it was missed by a prior migration.
     */
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('news_articles', 'image_url')) {
                $table->string('image_url', 500)->nullable()->after('description');
            }

            if (! Schema::hasColumn('news_articles', 'ai_summary_es')) {
                $table->text('ai_summary_es')->nullable()->after('ai_summary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            foreach (['ai_summary_es', 'image_url'] as $column) {
                if (Schema::hasColumn('news_articles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

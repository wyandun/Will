<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->text('article_url')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('keywords_matched')->nullable();
            $table->text('ai_summary')->nullable();
            $table->boolean('ai_selected')->default(false);
            // pending_ai = waiting for AI | pending_review = ready for superadmin | published | rejected
            $table->string('status')->default('pending_ai');
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->timestamp('fetched_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_articles');
    }
};

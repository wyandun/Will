<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feed posts — announcements, news, training materials, alerts.
     *
     * Visibility scoping:
     *   - global:    visible to all authenticated users
     *   - franchise: visible to users in the specified franchise
     *   - company:   visible to users of a specific company
     *
     * Posts support soft deletes so admins can recover accidentally deleted content.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('author_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Null = global post; set for franchise-scoped posts
            $table->foreignId('franchise_id')
                ->nullable()
                ->constrained('franchises')
                ->nullOnDelete();

            $table->string('title');
            $table->text('body');

            $table->string('type', 20)->comment('announcement | news | training | alert');
            $table->string('visibility', 20)->comment('global | franchise | company');

            $table->boolean('is_pinned')->default(false);

            // Optional file attachment
            $table->string('file_path')->nullable();
            $table->string('file_type', 50)->nullable()->comment('MIME type');
            $table->string('file_name')->nullable();

            // Scheduled publishing — null means publish immediately
            $table->timestamp('scheduled_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['visibility', 'franchise_id']);
            $table->index(['type', 'created_at']);
            $table->index('is_pinned');
            $table->index('scheduled_at');
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns to posts table:
     *   - image_url: CDN URL for an inline post image (different from file attachment)
     *   - file_url: CDN/storage URL for the attached file (complements file_path)
     *   - published_at: timestamp when the post was actually published
     *
     * franchise_id, title, type, visibility, is_pinned, file_path, file_type,
     * file_name, and scheduled_at already exist from the original migration.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (!Schema::hasColumn('posts', 'image_url')) {
                $table->string('image_url', 500)->nullable()->after('body');
            }

            if (!Schema::hasColumn('posts', 'file_url')) {
                $table->string('file_url', 500)->nullable()->after('file_path');
            }

            if (!Schema::hasColumn('posts', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('scheduled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            foreach (['image_url', 'file_url', 'published_at'] as $column) {
                if (Schema::hasColumn('posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

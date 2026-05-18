<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure posts has image_url, file_url, and published_at columns.
     *
     * These columns were added by 2026_04_28_000006_add_columns_to_posts_table but
     * that migration may have been skipped or recorded without running its DDL on
     * Railway (e.g. the DB was seeded from the legacy SQL dump which already had
     * those columns, causing hasColumn() guards to short-circuit, or the migration
     * batch record was inserted without executing the up() body).
     *
     * This migration is a safety net: it is fully idempotent and will never fail
     * even if the columns already exist.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'image_url')) {
                $table->string('image_url', 500)->nullable()->after('body');
            }

            if (! Schema::hasColumn('posts', 'file_url')) {
                $table->string('file_url', 500)->nullable()->after('file_path');
            }

            if (! Schema::hasColumn('posts', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('scheduled_at');
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty — these columns are load-bearing.
        // Dropping them here would destroy data.
    }
};

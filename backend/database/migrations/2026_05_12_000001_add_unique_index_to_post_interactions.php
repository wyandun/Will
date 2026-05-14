<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a composite index on (post_id, user_id, type) to support
     * the lockForUpdate query in FeedService::react() and speed up
     * interaction lookups across all interaction types.
     */
    public function up(): void
    {
        Schema::table('post_interactions', function (Blueprint $table) {
            $table->index(['post_id', 'user_id', 'type'], 'idx_post_interactions_post_user_type');
        });
    }

    public function down(): void
    {
        Schema::table('post_interactions', function (Blueprint $table) {
            $table->dropIndex('idx_post_interactions_post_user_type');
        });
    }
};

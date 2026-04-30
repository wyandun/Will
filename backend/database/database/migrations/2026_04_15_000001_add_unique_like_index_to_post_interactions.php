<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add a partial unique index on post_interactions so a user cannot like
     * the same post more than once.
     *
     * This uses a raw PostgreSQL partial index because Laravel's Blueprint
     * does not support WHERE clauses on unique indexes natively.
     * The constraint only applies to rows where type = 'like'; comments and
     * shares are intentionally excluded.
     */
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX unique_like_per_user
             ON post_interactions (post_id, user_id)
             WHERE type = \'like\''
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_like_per_user');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing B-tree indexes to improve query performance on the two
     * most-accessed list endpoints: GET /franchises and GET /companies.
     *
     * Indexes added:
     *   - franchises.deleted_at  — SoftDeletes appends WHERE deleted_at IS NULL
     *     on every query; without an index PostgreSQL does a sequential scan.
     *   - companies.deleted_at   — same reason as above.
     *
     * Both additions are guarded with a raw existence check so re-running the
     * migration on an environment where indexes already exist is safe.
     */
    public function up(): void
    {
        // franchises.deleted_at
        if (! $this->indexExists('franchises', 'franchises_deleted_at_index')) {
            Schema::table('franchises', function (Blueprint $table) {
                $table->index('deleted_at');
            });
        }

        // companies.deleted_at
        if (! $this->indexExists('companies', 'companies_deleted_at_index')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->index('deleted_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('franchises', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });
    }

    /**
     * Check whether a named index already exists in PostgreSQL's catalog.
     * Prevents "index already exists" errors on idempotent runs.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        return (bool) DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$table, $indexName]
        );
    }
};

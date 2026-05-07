<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing database indexes for performance and uniqueness enforcement.
     *
     * Each index is guarded with an existence check so this migration is
     * idempotent — safe to run even if some indexes already exist (e.g.
     * Spatie may have already created the permissions unique index).
     *
     * Indexes created:
     *   - permissions(name, guard_name)              — Spatie may already have this
     *   - user_permissions(user_id, module)          — one permission row per module per user
     *   - assessments(assigned_to_user_id)           — filter by assigned reviewer
     *   - assessments(contact_id)                    — already added in create migration
     *   - assessments(status, form_type)             — already added in create migration
     *   - process_maps(company_id, type)             — already exists as unique in create migration
     *   - client_trackings unique constraint         — already added in 2026_04_15_000003
     *   - event_shares(event_id, user_id)            — prevent duplicate shares
     */
    public function up(): void
    {
        // permissions(name, guard_name) — Spatie usually creates this; skip if exists
        if (! $this->indexExists('permissions', 'permissions_name_guard_name_unique')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->unique(['name', 'guard_name']);
            });
        }

        // user_permissions(user_id, module) — one permission row per user per module
        if (! $this->indexExists('user_permissions', 'user_permissions_user_id_module_unique')) {
            Schema::table('user_permissions', function (Blueprint $table) {
                $table->unique(['user_id', 'module']);
            });
        }

        // event_shares(event_id, user_id) — prevent duplicate share records
        if (! $this->indexExists('event_shares', 'event_shares_event_id_user_id_unique')) {
            Schema::table('event_shares', function (Blueprint $table) {
                $table->unique(['event_id', 'user_id']);
            });
        }

        // All other indexes listed in the task spec are already created
        // in their respective create/alter migrations:
        //   idx_assessments_assigned → assessments create migration
        //   idx_assessments_contact  → assessments create migration
        //   idx_assessments_status   → assessments create migration
        //   process_maps unique(company_id, type) → process_maps create migration
        //   unique_tracking_per_company_item_period → 2026_04_15_000003
    }

    public function down(): void
    {
        if ($this->indexExists('event_shares', 'event_shares_event_id_user_id_unique')) {
            Schema::table('event_shares', function (Blueprint $table) {
                $table->dropUnique(['event_id', 'user_id']);
            });
        }

        if ($this->indexExists('user_permissions', 'user_permissions_user_id_module_unique')) {
            Schema::table('user_permissions', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'module']);
            });
        }

        // Do NOT drop the permissions unique index — Spatie owns it
    }

    /**
     * Check whether a named index already exists.
     * Uses pg_indexes on PostgreSQL; falls back to a Schema call on SQLite.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'pgsql') {
            return (bool) DB::selectOne(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $indexName]
            );
        }

        return collect(DB::select("PRAGMA index_list($table)"))
            ->contains('name', $indexName);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add a partial unique index on repositories so each company can have at
     * most one repository per sub-franchise (where sub_franchise_id IS NOT NULL).
     *
     * Company-level repositories (sub_franchise_id IS NULL) are handled by the
     * separate unique_company_level_repository index.
     *
     * Laravel's Blueprint::unique() does not support partial indexes, so a raw
     * PostgreSQL statement is required.
     */
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX unique_sub_franchise_repository
             ON repositories (company_id, sub_franchise_id)
             WHERE sub_franchise_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_sub_franchise_repository');
    }
};

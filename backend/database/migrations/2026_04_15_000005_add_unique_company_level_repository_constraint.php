<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add a partial unique index on repositories so each company can have at
     * most one company-level repository (where sub_franchise_id IS NULL).
     *
     * Sub-franchise repositories (sub_franchise_id IS NOT NULL) are excluded
     * from this constraint — a company can have multiple sub-franchise repos.
     *
     * Laravel's Blueprint::unique() does not support partial indexes, so a raw
     * PostgreSQL statement is required.
     */
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX unique_company_level_repository
             ON repositories (company_id)
             WHERE sub_franchise_id IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_company_level_repository');
    }
};

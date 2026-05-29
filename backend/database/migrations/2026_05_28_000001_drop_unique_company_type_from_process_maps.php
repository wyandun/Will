<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops the unique (company_id, type) constraint on process_maps.
     *
     * The "exactly two maps per company" rule is being relaxed: companies may
     * now hold an arbitrary number of process maps so administrators can model
     * additional process domains beyond the original franquiciadora/franquiciada
     * pair. The two auto-created maps remain seeded on Close Deal — they are
     * simply no longer the only ones allowed.
     *
     * Using the column-array form of dropUnique() lets Laravel resolve the
     * auto-generated index name across drivers (PostgreSQL and SQLite differ).
     */
    public function up(): void
    {
        Schema::table('process_maps', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('process_maps', function (Blueprint $table): void {
            $table->unique(['company_id', 'type']);
        });
    }
};

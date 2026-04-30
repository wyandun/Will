<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `year` column to client_trackings and a unique constraint combining
     * company_id + catalog_item_id + month_number + year.
     *
     * Without `year`, a monthly deliverable tracked in January 2025 could not
     * be distinguished from January 2026, causing duplicate key collisions or
     * incorrect progress reporting across fiscal years.
     *
     * SMALLINT is sufficient (e.g. 2025, 2026) and saves space vs INT.
     * Nullable to remain compatible with one-time (non-monthly) deliverables
     * where month_number is also null.
     */
    public function up(): void
    {
        Schema::table('client_trackings', function (Blueprint $table) {
            $table->smallInteger('year')->unsigned()->nullable()
                ->after('month_number')
                ->comment('Calendar year for monthly recurring deliverables; null for one-time');

            // Prevent duplicate tracking rows for the same deliverable in the
            // same company / month / year combination.
            // NULL values are distinct in PostgreSQL so one-time deliverables
            // (month_number = null, year = null) are unaffected.
            $table->unique(
                ['company_id', 'catalog_item_id', 'month_number', 'year'],
                'unique_tracking_per_company_item_period'
            );
        });
    }

    public function down(): void
    {
        Schema::table('client_trackings', function (Blueprint $table) {
            $table->dropUnique('unique_tracking_per_company_item_period');
            $table->dropColumn('year');
        });
    }
};

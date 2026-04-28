<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No-op migration: the `year` column was already added to client_trackings
     * in migration 2026_04_15_000003_add_year_to_client_trackings.php,
     * which also created the unique_tracking_per_company_item_period constraint.
     *
     * This file exists to maintain the numbered sequence for documentation purposes.
     * up() and down() are intentionally empty.
     */
    public function up(): void
    {
        // year column already exists — added in 2026_04_15_000003
    }

    public function down(): void
    {
        // nothing to revert
    }
};

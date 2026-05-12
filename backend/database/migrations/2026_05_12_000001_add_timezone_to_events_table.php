<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `timezone` column to events.
     *
     * Stores the IANA timezone identifier used when the event was created
     * (e.g. "America/New_York"). Used by the frontend to display times
     * in the correct local timezone.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'timezone')) {
                $table->string('timezone', 60)->nullable()->after('end_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
    }
};

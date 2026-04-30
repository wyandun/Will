<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `type` column to events.
     *
     * Categorizes events for FullCalendar filtering:
     *   casual     — personal or informal event (default)
     *   meeting    — scheduled business meeting
     *   deadline   — task or deliverable deadline
     *   reminder   — automated or manual reminder
     *   training   — training session
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'type')) {
                $table->string('type', 20)->default('casual')->after('color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // RFC 5545 RRULE string (e.g. "FREQ=WEEKLY;BYDAY=MO"). Null = non-recurring.
            $table->string('rrule', 500)->nullable()->after('type');

            // Minutes before start_at to fire a reminder. Null = no reminder.
            $table->integer('reminder_minutes')->nullable()->after('rrule');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['rrule', 'reminder_minutes']);
        });
    }
};

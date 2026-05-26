<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot between events and users for the Add Guests feature.
     *
     * rsvp_status tracks each guest's RSVP independently:
     *   pending   — invited, no response yet (default)
     *   accepted  — guest confirmed attendance
     *   declined  — guest declined
     *   tentative — guest may attend
     */
    public function up(): void
    {
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('rsvp_status', 20)->default('pending')->comment(
                'pending | accepted | declined | tentative'
            );

            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
    }
};

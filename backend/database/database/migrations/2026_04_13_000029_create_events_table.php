<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Calendar events — used with FullCalendar on the frontend.
     *
     * Visibility scoping:
     *   private   → only the creator sees it (unless shared via event_shares)
     *   franchise → visible to all users in the creator's franchise
     *   public    → visible to all authenticated users in the system
     *
     * all_day=true means start_at and end_at represent dates only (time is ignored).
     * color is a hex string used by FullCalendar for display (#FF5733, etc.).
     *
     * Soft deletes so users can recover accidentally deleted events.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->timestamp('start_at');
            $table->timestamp('end_at');

            $table->string('location')->nullable();
            $table->string('color', 10)->nullable()->comment('Hex color for FullCalendar display');

            $table->string('visibility', 20)->default('private')->comment(
                'private | franchise | public'
            );

            $table->boolean('all_day')->default(false);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'start_at']);
            $table->index(['visibility', 'start_at']);
            $table->index('start_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

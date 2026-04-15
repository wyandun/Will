<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Event shares — grants specific users access to a private event.
     * Used when the event creator wants to share a private event with
     * selected individuals without changing global visibility.
     *
     * No updated_at — share grants are immutable (revoke by deleting the row).
     */
    public function up(): void
    {
        Schema::create('event_shares', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // No updated_at — shares are granted or revoked, not edited
            $table->timestamp('created_at')->useCurrent();

            // A user cannot be shared the same event twice
            $table->unique(['event_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_shares');
    }
};

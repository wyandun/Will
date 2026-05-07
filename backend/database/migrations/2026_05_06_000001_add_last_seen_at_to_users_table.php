<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add last_seen_at to users for online-presence tracking.
     *
     * Updated by TrackUserPresence middleware on every authenticated API request
     * (throttled to at most once per 60 seconds per user). Used by FeedService
     * to determine "Online Now" (within 5 min) vs "Recently Active" panels.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'last_seen_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('invitation_accepted_at');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_seen_at')) {
                $table->dropIndex(['last_seen_at']);
                $table->dropColumn('last_seen_at');
            }
        });
    }
};

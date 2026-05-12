<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add invitation metadata to the users table:
     *   - invited_by:             FK → users.id — who sent the invitation
     *   - invitation_expires_at:  when the invitation link expires (7 days by default)
     *
     * The base invitation_token and invitation_accepted_at columns
     * were added in migration 2026_04_28_000003.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'invited_by')) {
                $table->unsignedBigInteger('invited_by')
                    ->nullable()
                    ->after('invitation_accepted_at');

                $table->foreign('invited_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'invitation_expires_at')) {
                $table->timestamp('invitation_expires_at')
                    ->nullable()
                    ->after('invited_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'invitation_expires_at')) {
                $table->dropColumn('invitation_expires_at');
            }

            if (Schema::hasColumn('users', 'invited_by')) {
                $table->dropForeign(['invited_by']);
                $table->dropColumn('invited_by');
            }
        });
    }
};

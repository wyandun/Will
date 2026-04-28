<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns to users table:
     *   - birth_date: optional date of birth for profile completeness
     *   - invitation_token: unique token sent via email for account invitations
     *   - invitation_accepted_at: timestamp when the invitation was accepted
     *   - deleted_at: soft deletes for user accounts
     *
     * remember_token is already present via rememberToken() in the original migration.
     * Each column is guarded with hasColumn() to make this migration idempotent.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('bio');
            }

            if (!Schema::hasColumn('users', 'invitation_token')) {
                $table->string('invitation_token', 100)->unique()->nullable()->after('birth_date');
            }

            if (!Schema::hasColumn('users', 'invitation_accepted_at')) {
                $table->timestamp('invitation_accepted_at')->nullable()->after('invitation_token');
            }

            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('invitation_accepted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('users', 'invitation_accepted_at')) {
                $table->dropColumn('invitation_accepted_at');
            }

            if (Schema::hasColumn('users', 'invitation_token')) {
                $table->dropColumn('invitation_token');
            }

            if (Schema::hasColumn('users', 'birth_date')) {
                $table->dropColumn('birth_date');
            }
        });
    }
};

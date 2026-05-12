<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rename the ambiguous `invited_by` FK column to `inviter_id`.
 *
 * The old name collided with the Eloquent relationship method `invitedBy()`,
 * making $user->invited_by (column) vs $user->invitedBy (relation) confusing
 * and error-prone in eager-load contexts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'invited_by')) {
                // Drop the old FK constraint before renaming.
                $table->dropForeign(['invited_by']);
                $table->renameColumn('invited_by', 'inviter_id');

                // Re-add the FK on the renamed column.
                $table->foreign('inviter_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'inviter_id')) {
                $table->dropForeign(['inviter_id']);
                $table->renameColumn('inviter_id', 'invited_by');

                $table->foreign('invited_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });
    }
};

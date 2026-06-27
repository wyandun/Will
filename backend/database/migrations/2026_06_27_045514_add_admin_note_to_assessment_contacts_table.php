<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add admin_note to assessment_contacts for internal audit annotations.
     *
     * This field is exclusively written by admin_sm users to leave internal
     * directives or observations about a submitted assessment. It is separate
     * from the public 'notes' field filled by the contact themselves.
     *
     * admin_noted_by_user_id and admin_noted_at track who wrote the note and when,
     * preserving a basic audit trail without a full versioned history.
     */
    public function up(): void
    {
        Schema::table('assessment_contacts', function (Blueprint $table) {
            $table->text('admin_note')->nullable()->after('notes');

            $table->foreignId('admin_noted_by_user_id')
                ->nullable()
                ->after('admin_note')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('admin_noted_at')->nullable()->after('admin_noted_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_contacts', function (Blueprint $table) {
            $table->dropForeign(['admin_noted_by_user_id']);
            $table->dropColumn(['admin_note', 'admin_noted_by_user_id', 'admin_noted_at']);
        });
    }
};

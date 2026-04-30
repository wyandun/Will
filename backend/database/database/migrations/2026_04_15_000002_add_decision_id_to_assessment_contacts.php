<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add decision_id FK to assessment_contacts referencing assessment_decisions.
     *
     * This links a contact record to the structured decision catalog entry,
     * replacing any free-text decision fields that may have been stored in `data`.
     * Nullable because existing rows have no decision set yet, and new contacts
     * start without a decision until reviewed.
     */
    public function up(): void
    {
        Schema::table('assessment_contacts', function (Blueprint $table) {
            $table->foreignId('decision_id')
                ->nullable()
                ->after('reviewed_at')
                ->constrained('assessment_decisions')
                ->nullOnDelete();

            $table->index('decision_id');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_contacts', function (Blueprint $table) {
            $table->dropForeign(['decision_id']);
            $table->dropIndex(['decision_id']);
            $table->dropColumn('decision_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal entries — the core double-entry bookkeeping record.
     *
     * CRITICAL BUSINESS RULE: If ai_confidence < 0.70, the entry status
     * must start as 'pending_review' and require manual approval before
     * it can move to 'approved'. This is enforced in the Service layer
     * (JournalEntryService), but the schema supports it via the status column.
     *
     * financial_document_id is null for manually created entries.
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Null for manually created entries
            $table->foreignId('financial_document_id')
                ->nullable()
                ->constrained('financial_documents')
                ->nullOnDelete();

            $table->text('description');
            $table->date('date');

            $table->string('status', 20)->default('pending_review')->comment(
                'pending_review | approved | rejected'
            );

            // AI confidence score from OpenAI extraction (0.00–1.00).
            // If < 0.70, remains in pending_review until manually approved.
            $table->decimal('ai_confidence', 3, 2)->nullable()
                ->comment('< 0.70 requires manual review before approval');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'date']);
            $table->index('financial_document_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};

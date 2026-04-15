<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bank transactions — rows extracted from uploaded bank statements.
     *
     * Matching columns replace the legacy transaction_matches table.
     * Matching links a raw bank transaction to the journal entry that
     * accounts for it, with a confidence score from the AI matcher.
     *
     * match_status lifecycle:
     *   unmatched → matched   (AI or manual match found)
     *   unmatched → ignored   (user explicitly marks as not relevant)
     */
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('financial_document_id')
                ->constrained('financial_documents')
                ->cascadeOnDelete();

            $table->date('date');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('type', 10)->comment('debit | credit');

            // --- Replaces the legacy transaction_matches table ---

            // Null until a matching journal entry is found
            $table->foreignId('matched_journal_entry_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete();

            // AI confidence for the match (0.00–1.00); null if unmatched
            $table->decimal('match_confidence', 3, 2)->nullable();

            $table->string('match_status', 20)->default('unmatched')->comment(
                'unmatched | matched | ignored'
            );

            $table->timestamp('matched_at')->nullable();

            $table->foreignId('matched_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('The user who confirmed a manual match; null for AI matches');

            $table->timestamps();

            $table->index(['company_id', 'match_status']);
            $table->index(['company_id', 'date']);
            $table->index('financial_document_id');
            $table->index('matched_journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};

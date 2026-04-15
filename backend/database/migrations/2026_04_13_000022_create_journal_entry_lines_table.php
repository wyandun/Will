<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal entry lines — the individual debit/credit lines of a journal entry.
     *
     * Standard double-entry bookkeeping: every journal entry must have
     * total debits = total credits. This is enforced in JournalEntryService.
     *
     * No timestamps (lines are atomic with their parent entry).
     * No soft deletes (lines are deleted with their parent entry).
     */
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_entry_id')
                ->constrained('journal_entries')
                ->cascadeOnDelete();

            $table->foreignId('account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            $table->string('type', 10)->comment('debit | credit');

            // Precision for financial amounts: 13 integer digits + 2 decimal
            $table->decimal('amount', 15, 2);

            $table->string('description')->nullable();

            // No timestamps — lines live and die with their parent entry
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};

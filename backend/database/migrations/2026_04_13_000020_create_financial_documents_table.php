<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Financial documents — uploaded files that trigger AI processing.
     *
     * On upload, the ProcessFinancialDocument job is dispatched to:
     *   1. Run OCR (Tesseract) or parse native PDF text
     *   2. Send to OpenAI for transaction extraction
     *   3. Create bank_transactions rows
     *   4. Create journal_entries (with ai_confidence score)
     *
     * processing_status tracks the async job lifecycle.
     * Soft deletes preserve the audit trail.
     */
    public function up(): void
    {
        Schema::create('financial_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('type', 20)->comment('bank_statement | invoice | receipt');

            $table->string('file_path');
            $table->string('file_type', 50)->comment('MIME type');
            $table->string('original_filename');

            // AI processing lifecycle
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_status', 20)->default('pending')->comment(
                'pending | processing | completed | failed'
            );

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'processing_status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_documents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Financial documents — pure file vault for company financial records.
     *
     * v2 simplification: all AI/OCR processing has been removed. This table
     * is now only a file repository. QuickBooks Online handles all accounting
     * logic. Admins upload statements, invoices, and receipts here for reference.
     */
    public function up(): void
    {
        Schema::create('financial_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('type', 20)->comment('bank_statement | invoice | receipt | other');

            $table->string('file_path');
            $table->string('file_type', 60)->comment('MIME type');
            $table->string('original_filename');

            // Optional period reference for bank statements (e.g. '2026-04')
            $table->string('period_label', 40)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'type', 'created_at'], 'financial_documents_company_type_created_idx');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_documents');
    }
};

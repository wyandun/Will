<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contracts — managed via DocuSeal (self-hosted e-signing).
     *
     * Three signers reflect the SM internal approval workflow:
     *   elaborated_by  → the consultant who drafted the contract
     *   reviewed_by    → the reviewer (admin_sm)
     *   approved_by    → the final approver (superadmin or senior admin_sm)
     *
     * BB users can read contracts for their sponsored company (read-only).
     * signed_document_url replaces the legacy onedrive_url field.
     *
     * Soft deletes to preserve audit trail.
     */
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('status', 20)->default('draft')->comment(
                'draft | sent | signed | expired | cancelled'
            );

            // DocuSeal integration fields
            $table->string('docuseal_template_id')->nullable();
            $table->string('docuseal_submission_id')->nullable();

            // Three-signer approval workflow
            $table->foreignId('elaborated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // URL returned by DocuSeal after all parties sign
            $table->string('signed_document_url')->nullable()
                ->comment('Replaces legacy onedrive_url');

            $table->timestamp('signed_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};

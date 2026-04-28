<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assessments — structured evaluation results linked to a contact or BB application.
     *
     * form_type distinguishes assessment versions (1 = SB Assessment 1, etc.).
     * answers and scores store the full response payload as JSON.
     * score_band is a categorical label derived from score_overall (e.g. 'high', 'medium').
     *
     * AI confidence below 0.70 triggers manual review workflow.
     * pdf_path_es and pdf_path_en store language-specific generated PDFs.
     *
     * Soft deletes preserve the audit trail.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();

            // Either contact_id or bb_application_id will be set (not both)
            $table->foreignId('contact_id')
                ->nullable()
                ->constrained('assessment_contacts')
                ->nullOnDelete();

            $table->foreignId('bb_application_id')
                ->nullable()
                ->constrained('bb_applications')
                ->nullOnDelete();

            // Discriminator for assessment form version
            $table->smallInteger('form_type')->comment('1 = SB Assessment 1, 2 = SB Assessment 2, etc.');

            $table->char('lang', 2)->default('es')->comment('es | en');

            $table->string('status', 20)->default('draft')->comment(
                'draft | submitted | reviewed | approved | rejected'
            );

            // Answer and scoring payloads
            $table->json('answers')->nullable();
            $table->json('scores')->nullable();
            $table->decimal('score_overall', 5, 2)->nullable();
            $table->string('score_band', 20)->nullable()->comment('high | medium | low');

            // AI-generated content
            $table->text('narrative')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('critical_flags')->nullable();
            $table->json('recommended_services')->nullable();

            // Generated PDF paths per language
            $table->string('pdf_path_es', 500)->nullable();
            $table->string('pdf_path_en', 500)->nullable();

            // Review workflow
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();

            // Audit / forensic field
            $table->string('ip_address', 45)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'form_type']);
            $table->index('assigned_to_user_id', 'idx_assessments_assigned');
            $table->index('contact_id', 'idx_assessments_contact');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public form submissions — no authentication required.
     * Assessment 1 is split into 4 stages: operational maturity, franchise
     * alignment, BB simulator, and results (with downloadable PDF).
     *
     * Stage data is stored in separate JSONB columns so each stage can be
     * saved independently as the user progresses.
     *
     * When status becomes 'converted', converted_company_id is populated
     * via the "Close Deal" action which also creates the company record,
     * two process maps, and the default user account.
     */
    public function up(): void
    {
        Schema::create('assessment_contacts', function (Blueprint $table) {
            $table->id();

            $table->string('type', 30)->comment('sb_assessment_1 | sb_assessment_3 | bb_application');

            // Only populated for sb_assessment_1 — tracks which stage the user is on
            $table->string('current_stage', 30)->nullable()->comment(
                'operational_maturity | franchise_alignment | bb_simulator | results'
            );

            // Stage-specific JSONB payloads (sb_assessment_1 only)
            $table->jsonb('stage_1_data')->nullable()->comment('Operational maturity — 7 dimensions (answers + scores)');
            $table->jsonb('stage_2_data')->nullable()->comment('Franchise alignment answers');
            $table->jsonb('stage_3_data')->nullable()->comment('BB simulator inputs + 5-year projection output');
            $table->jsonb('stage_4_data')->nullable()->comment('Final result snapshot + PDF metadata');

            // Generic payload for Assessment 3 and BB application
            $table->jsonb('data')->nullable();

            $table->decimal('score', 5, 2)->nullable()->comment('Final aggregated score');
            $table->jsonb('score_breakdown')->nullable()->comment('Per-dimension breakdown');

            // Path to the generated PDF produced at stage 4
            $table->string('result_pdf_path', 255)->nullable();

            $table->string('status', 20)->default('in_progress')->comment(
                'in_progress | pending | reviewed | approved | rejected | converted'
            );

            // Set when Close Deal is executed; triggers company + process map creation
            $table->foreignId('converted_company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('current_stage');
            $table->index(['type', 'status']);
            $table->index('created_at');

            // GIN indexes for fast key searches inside stage JSONB columns
            $table->rawIndex('USING GIN (stage_1_data)', 'assessment_contacts_stage_1_data_gin');
            $table->rawIndex('USING GIN (stage_2_data)', 'assessment_contacts_stage_2_data_gin');
            $table->rawIndex('USING GIN (stage_3_data)', 'assessment_contacts_stage_3_data_gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_contacts');
    }
};

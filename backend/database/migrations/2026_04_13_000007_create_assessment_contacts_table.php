<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public form submissions — no authentication required.
     * Covers Assessment 1 (63 questions / 9 dimensions), Assessment 2, and BB applications.
     *
     * All form answers are stored in the JSONB `data` column for flexibility,
     * since each assessment type has a different question structure.
     *
     * When status becomes 'converted', the converted_company_id is populated
     * via the "Close Deal" action which also creates the company record,
     * two process maps, and the default user account.
     */
    public function up(): void
    {
        Schema::create('assessment_contacts', function (Blueprint $table) {
            $table->id();

            $table->string('type', 30)->comment('sb_assessment_1 | sb_assessment_2 | bb_application');

            // All form answers stored as JSONB for flexibility across assessment types
            $table->jsonb('data');

            // Assessment 1 has scoring across 9 dimensions (A–I)
            $table->decimal('score', 5, 2)->nullable()->comment('Overall score for Assessment 1');
            $table->jsonb('score_breakdown')->nullable()->comment('Per-dimension scores {A: x, B: x, ...}');

            $table->string('status', 20)->default('pending')->comment(
                'pending | reviewed | approved | rejected | converted'
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
            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_contacts');
    }
};

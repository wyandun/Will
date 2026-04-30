<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog of possible decisions for assessments.
     * Replaces free-text varchar decisions — ensures consistency and translatability.
     * Seeded with the standard SM decision set via DatabaseSeeder.
     */
    public function up(): void
    {
        Schema::create('assessment_decisions', function (Blueprint $table) {
            $table->id();

            // Short machine-readable key, e.g. 'approved_with_conditions'
            $table->string('code', 60)->unique();

            $table->string('label_es');
            $table->string('label_en');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_decisions');
    }
};

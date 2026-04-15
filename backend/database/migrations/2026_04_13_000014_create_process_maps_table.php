<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Process maps — BPMN map containers per company.
     *
     * CRITICAL BUSINESS RULE: Every company must have EXACTLY two process maps:
     *   - type='franquiciadora' → the franchisor-facing map (SM and company admins)
     *   - type='franquiciada'  → the franchisee-facing map (sub-franchise owners)
     *
     * Both maps are auto-created when a company is registered via "Close Deal".
     * The unique constraint on (company_id, type) enforces the exactly-two rule
     * at the database level.
     *
     * Sub-franchise owners see only the 'franquiciada' map of their parent company.
     */
    public function up(): void
    {
        Schema::create('process_maps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Enforces exactly 2 maps per company at DB level
            $table->string('type', 20)->comment('franquiciadora | franquiciada');

            $table->string('name_es');
            $table->string('name_en');

            $table->timestamps();

            // This unique constraint is the DB-level enforcement of the business rule
            $table->unique(['company_id', 'type']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_maps');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sub-sub-processes — optional third level of the process tree.
     *
     * Some sub_processes are leaves (no children). Others have sub_sub_processes
     * that represent finer-grained procedures with their own BPMN diagrams,
     * walkthrough narrations, and process documents.
     *
     * manual_document_id is a deferred FK to process_documents. The FK constraint
     * is added in 2026_04_20_000003 after process_documents already exists.
     * This resolves the circular dependency between the two tables.
     *
     * walkthrough_es / walkthrough_en: JSONB arrays of step objects keyed to
     * BPMN shape IDs, used by the guided walkthrough feature.
     */
    public function up(): void
    {
        Schema::create('sub_sub_processes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sub_process_id')
                ->constrained('sub_processes')
                ->cascadeOnDelete();

            $table->string('code', 40)->unique()->comment('e.g. GTH-P01-S01');

            $table->string('name_es', 200);
            $table->string('name_en', 200);

            $table->text('bpmn_xml_es')->nullable();
            $table->text('bpmn_xml_en')->nullable()->comment('Filled by TranslateBpmnXml job');

            $table->jsonb('walkthrough_es')->nullable()->comment('Step-by-step narration tied to BPMN shape IDs');
            $table->jsonb('walkthrough_en')->nullable();

            // Deferred FK → process_documents — added in 2026_04_20_000003
            $table->unsignedBigInteger('manual_document_id')->nullable()
                ->comment('Shortcut to the main manual document for this sub-sub-process');

            $table->integer('order_index')->default(0);

            $table->timestamps();

            $table->index('sub_process_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_sub_processes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sub-processes — the actual BPMN diagrams.
     *
     * Each sub-process has two BPMN XML fields (ES and EN) to support
     * the bilingual requirement. The AI translation job (TranslateBpmnXml)
     * populates bpmn_xml_en from bpmn_xml_es when a new diagram is uploaded.
     *
     * Replaces old bpmn_xml (no language) + flow_data fields.
     */
    public function up(): void
    {
        Schema::create('sub_processes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('process_id')
                ->constrained('processes')
                ->cascadeOnDelete();

            // Full sub-process identifier, e.g. 'GTH-P01'
            $table->string('code', 30)->comment('e.g. GTH-P01');

            $table->string('name_es');
            $table->string('name_en');

            // Bilingual BPMN XML — replaces legacy bpmn_xml + flow_data
            $table->text('bpmn_xml_es')->nullable()->comment('BPMN diagram in Spanish');
            $table->text('bpmn_xml_en')->nullable()->comment('BPMN diagram in English (AI-translated)');

            $table->unsignedSmallInteger('order_index')->default(0);

            $table->timestamps();

            $table->index(['process_id', 'order_index']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_processes');
    }
};

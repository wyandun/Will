<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds manual_document_id to sub_processes as a bare unsignedBigInteger.
     *
     * The FK constraint pointing to process_documents cannot be added here
     * because process_documents is created in migration _000018 which runs
     * before sub_processes (_000017). The circular dependency is resolved by
     * adding the FK separately in 2026_04_20_000003, after both tables exist.
     *
     * walkthrough_es / walkthrough_en are also added here so sub_processes
     * has the same guided walkthrough capability as sub_sub_processes.
     */
    public function up(): void
    {
        Schema::table('sub_processes', function (Blueprint $table) {
            // Deferred FK → process_documents — wired in 2026_04_20_000003
            $table->unsignedBigInteger('manual_document_id')->nullable()
                ->after('bpmn_xml_en')
                ->comment('Shortcut to the main manual document for this sub-process');

            $table->jsonb('walkthrough_es')->nullable()
                ->after('manual_document_id')
                ->comment('Step-by-step narration tied to BPMN shape IDs');

            $table->jsonb('walkthrough_en')->nullable()
                ->after('walkthrough_es');
        });
    }

    public function down(): void
    {
        Schema::table('sub_processes', function (Blueprint $table) {
            $table->dropColumn(['manual_document_id', 'walkthrough_es', 'walkthrough_en']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wires the deferred manual_document_id FKs now that both sub_processes,
     * sub_sub_processes, and process_documents all exist.
     *
     * This resolves the circular dependency in the process tree:
     *   sub_processes.manual_document_id     → process_documents.id
     *   sub_sub_processes.manual_document_id → process_documents.id
     *
     * Using nullOnDelete so that deleting a process document does not cascade
     * to the sub-process — the reference simply becomes null and can be updated.
     */
    public function up(): void
    {
        Schema::table('sub_processes', function (Blueprint $table) {
            $table->foreign('manual_document_id')
                ->references('id')
                ->on('process_documents')
                ->nullOnDelete();
        });

        Schema::table('sub_sub_processes', function (Blueprint $table) {
            $table->foreign('manual_document_id')
                ->references('id')
                ->on('process_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sub_sub_processes', function (Blueprint $table) {
            $table->dropForeign(['manual_document_id']);
        });

        Schema::table('sub_processes', function (Blueprint $table) {
            $table->dropForeign(['manual_document_id']);
        });
    }
};

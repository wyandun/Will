<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Process documents — supporting files attached to any level of the process tree.
     *
     * Polymorphic design: a document can belong to a Process, SubProcess, or SubSubProcess.
     * documentable_type stores the morph map alias (process | sub_process | sub_sub_process).
     * documentable_id stores the PK of the owning model.
     *
     * Document type codes:
     *   MP  = Manual de Procedimiento
     *   FOR = Formato
     *   MN  = Manual
     *   IN  = Instructivo
     *   AN  = Anexo
     *   PO  = Política
     *   PR  = Procedimiento
     *   CR  = Criterio / Referencia
     *
     * code format: '{PROCESS_CODE}-{TYPE}-{sequence}', e.g. 'GTH-P01-FOR-01'
     * file_url replaces legacy onedrive_url.
     *
     * Versioning: new version → new row with parent_id set, is_current=true on latest.
     *
     * NOTE: The FK from sub_processes.manual_document_id and
     * sub_sub_processes.manual_document_id back to this table is added in a
     * separate migration after both tables exist (circular reference resolution).
     */
    public function up(): void
    {
        Schema::create('process_documents', function (Blueprint $table) {
            $table->id();

            // Polymorphic parent: process | sub_process | sub_sub_process
            $table->string('documentable_type', 80);
            $table->unsignedBigInteger('documentable_id');

            $table->string('code', 60)->comment('e.g. GTH-P01-FOR-01');

            $table->string('type', 5)->comment('MP | FOR | MN | IN | AN | PO | PR | CR');

            $table->string('title_es', 200);
            $table->string('title_en', 200);
            $table->text('description')->nullable();

            // Storage URL — replaces legacy onedrive_url
            $table->string('file_url', 500)->nullable();

            $table->unsignedSmallInteger('version')->default(1);

            // Self-referential FK for version chain
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('process_documents')
                ->nullOnDelete();

            $table->boolean('is_current')->default(true);

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id']);
            $table->unique(['code', 'version']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_documents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repository documents — files stored within a company or sub-franchise repository.
     *
     * Three tabs map to three section values:
     *   setup  — Company Setup: legal, HR, certificates, marketing, SOPs
     *   process — Process Documents: manuals and forms linked to the process map
     *   record  — Records by Process: completed records uploaded by the client
     *
     * Versioning: when a new version is uploaded, a new row is created with
     * parent_id pointing to the previous version. Only the row with is_current=true
     * is shown by default.
     *
     * process_code stores a string reference like 'GTH-P01' instead of a FK
     * to sub_processes, because documents may reference process codes that
     * haven't been mapped yet (decoupled for flexibility).
     */
    public function up(): void
    {
        Schema::create('repository_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('repository_id')
                ->constrained('repositories')
                ->cascadeOnDelete();

            $table->string('section', 20)->comment('setup | process | record');
            $table->string('category', 30)->nullable()->comment(
                'legal | hr | certificates | marketing | sops | process_linked | record_linked'
            );

            // Loose reference to a process code (not a FK — decoupled for flexibility)
            $table->string('process_code', 40)->nullable()
                ->comment('e.g. GTH-P01 — required when section = process or record');

            // Record-specific fields (required when section = record)
            $table->date('record_date')->nullable()
                ->comment('Date the record covers — required when section = record');
            $table->string('record_period', 60)->nullable()
                ->comment('Optional label e.g. 2026-Q1');

            $table->string('title', 200);
            $table->text('description')->nullable();

            $table->string('file_path');
            $table->string('file_type', 50)->comment('MIME type');
            $table->unsignedBigInteger('file_size')->comment('bytes');

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('uploaded_by_type', 10)->comment('sm | client');

            $table->unsignedSmallInteger('version')->default(1);

            // Self-referential FK for version chain
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('repository_documents')
                ->nullOnDelete();

            // Only true for the latest version of each document
            $table->boolean('is_current')->default(true);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['repository_id', 'section', 'is_current'], 'repo_docs_repository_section_current_idx');
            $table->index(['section', 'category']);
            $table->index('process_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_documents');
    }
};

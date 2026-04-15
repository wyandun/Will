<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repository documents — files stored within a company or sub-franchise repository.
     *
     * Versioning: when a new version is uploaded, a new row is created with
     * parent_id pointing to the previous version. Only the row with is_current=true
     * is shown by default. Queries should always filter by is_current=true.
     *
     * process_code stores a string reference like 'GTH-P01' instead of a FK
     * to sub_processes, because documents may reference process codes that
     * haven't been mapped yet (decoupled for flexibility).
     *
     * Soft deletes preserve version history audit trail.
     */
    public function up(): void
    {
        Schema::create('repository_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('repository_id')
                ->constrained('repositories')
                ->cascadeOnDelete();

            $table->string('section', 20)->comment('setup | process');
            $table->string('category', 30)->comment(
                'legal | hr | certificates | marketing | sops | process_linked'
            );

            // Loose reference to a process code (not a FK — see docblock)
            $table->string('process_code', 30)->nullable()
                ->comment('e.g. GTH-P01 — used when category=process_linked');

            $table->string('title');
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

            $table->index(['repository_id', 'section', 'category']);
            $table->index(['repository_id', 'is_current']);
            $table->index('process_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_documents');
    }
};

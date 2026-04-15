<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Process documents — supporting files for a sub-process (manuals, forms, records, etc.).
     *
     * code format: '{PROCESS_CODE}-{TYPE_PREFIX}-{sequence}', e.g. 'GTH-P01-FOR-01'
     * file_url replaces legacy onedrive_url.
     *
     * Versioning follows the same pattern as repository_documents:
     * new version → new row with parent_id set, is_current=true on latest.
     *
     * Soft deletes preserve audit trail.
     */
    public function up(): void
    {
        Schema::create('process_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sub_process_id')
                ->constrained('sub_processes')
                ->cascadeOnDelete();

            // e.g. 'GTH-P01-FOR-01' where FOR = form, MAN = manual, REG = record, etc.
            $table->string('code', 40)->comment('e.g. GTH-P01-FOR-01');

            $table->string('type', 20)->comment('manual | form | record | policy | certificate');

            $table->string('title_es');
            $table->string('title_en');
            $table->text('description')->nullable();

            // Storage URL — replaces legacy onedrive_url
            $table->string('file_url')->nullable()->comment('Replaces legacy onedrive_url');

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

            $table->index(['sub_process_id', 'is_current']);
            $table->index('code');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_documents');
    }
};

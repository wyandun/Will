<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Project deliverables — individual work items generated from the catalog
     * when a project is created.
     *
     * Each row corresponds to one deliverable-level catalog item within the
     * assigned bundle/service. Dates are calculated sequentially:
     *   deliverable N starts when deliverable N-1 ends, based on estimated_hours
     *   from the catalog item (8 working hours = 1 business day).
     */
    public function up(): void
    {
        Schema::create('project_deliverables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            // The catalog deliverable this row was generated from
            $table->foreignId('catalog_item_id')
                ->constrained('catalog_items')
                ->restrictOnDelete();

            // Snapshot of the name at creation time (catalog may change later)
            $table->string('name');

            // Optional grouping label (service name the deliverable belongs to)
            $table->string('phase')->nullable();

            $table->date('estimated_start_date')->nullable();
            $table->date('estimated_end_date')->nullable();

            $table->string('status', 20)->default('pending')
                ->comment('pending | in_progress | completed | blocked');

            // Controls display order within the project
            $table->unsignedSmallInteger('order')->default(0);

            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_deliverables');
    }
};

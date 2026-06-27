<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projects — a service assignment linking a company to a catalog item.
     *
     * When a project is created, ProjectDeliverables are auto-generated
     * from the catalog hierarchy and scheduled sequentially from start_date.
     *
     * type mirrors CatalogLevel: bundle | service | deliverable
     * When type=bundle, all deliverables across the bundle's services are created.
     * When type=service, all deliverables of that service are created.
     * When type=deliverable, a single project deliverable is created.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('franchise_id')
                ->constrained('franchises')
                ->restrictOnDelete();

            // The root catalog item assigned (bundle, service, or deliverable)
            $table->foreignId('catalog_item_id')
                ->constrained('catalog_items')
                ->restrictOnDelete();

            $table->string('type', 20)->comment('bundle | service | deliverable');

            $table->date('start_date');

            $table->text('notes')->nullable();

            $table->string('status', 20)->default('active')
                ->comment('active | completed | paused | cancelled');

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['franchise_id', 'status']);
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

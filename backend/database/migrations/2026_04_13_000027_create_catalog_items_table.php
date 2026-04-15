<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog items — replaces the old packages + services + deliverables tables
     * and their two pivot tables (package_service, service_deliverable).
     *
     * Hierarchy via level + parent_id:
     *   bundle      → top-level package (parent_id = null)
     *   service     → belongs to a bundle (parent_id → bundle)
     *   deliverable → belongs to a service (parent_id → service)
     *
     * client_trackings references only deliverable-level items.
     * is_monthly=true marks recurring monthly deliverables.
     */
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();

            $table->string('level', 20)->comment('bundle | service | deliverable');

            // Self-referential: null for bundles; service.id for deliverables; bundle.id for services
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('catalog_items')
                ->nullOnDelete();

            $table->string('name_es');
            $table->string('name_en');
            $table->text('description_es')->nullable();
            $table->text('description_en')->nullable();

            // True for deliverables that repeat every month (monthly tracking)
            $table->boolean('is_monthly')->default(false);

            // Controls display order within the parent item
            $table->unsignedSmallInteger('order_index')->default(0);

            $table->timestamps();

            $table->index(['level', 'parent_id']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};

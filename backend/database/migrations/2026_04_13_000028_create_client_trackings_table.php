<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Client trackings — progress tracking for deliverables assigned to companies.
     *
     * Only deliverable-level catalog_items should be referenced here.
     * This is enforced in the Service layer (TrackingService) — the DB
     * stores the FK but cannot check the level discriminator natively.
     *
     * month_number is used for recurring monthly deliverables (is_monthly=true)
     * to distinguish Jan, Feb, ... rows for the same deliverable.
     */
    public function up(): void
    {
        Schema::create('client_trackings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Must reference a deliverable-level catalog item (enforced in service layer)
            $table->foreignId('catalog_item_id')
                ->constrained('catalog_items')
                ->restrictOnDelete();

            $table->string('status', 20)->default('pending')->comment(
                'pending | in_progress | review | completed | cancelled'
            );

            $table->date('estimated_start')->nullable();
            $table->date('estimated_end')->nullable();
            $table->date('actual_end')->nullable();

            // For is_monthly=true deliverables: 1=Jan, 2=Feb, ..., 12=Dec
            $table->unsignedTinyInteger('month_number')->nullable()
                ->comment('1–12 for monthly recurring deliverables; null for one-time');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'catalog_item_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_trackings');
    }
};

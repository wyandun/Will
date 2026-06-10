<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the remaining catalog metadata columns:
     *   - estimated_hours: workload estimate, primarily set on deliverables.
     *     Bundles and services derive their totals by summing descendants.
     *   - service_type:    classification used at the service level
     *     (individual | package | retainer).
     */
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->decimal('estimated_hours', 8, 2)
                ->nullable()
                ->default(0)
                ->after('is_monthly');

            $table->string('service_type', 20)
                ->nullable()
                ->after('estimated_hours');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn(['estimated_hours', 'service_type']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns to process_maps table:
     *   - description: optional text description of the process map
     *   - brand_color: hex color for the map's visual theme in the BPMN editor
     *   - logo_url: company logo URL overlaid on the map header
     *   - node_styles: JSON object with custom BPMN node style overrides
     *   - is_active: soft-toggle to hide a map without deleting it
     */
    public function up(): void
    {
        Schema::table('process_maps', function (Blueprint $table) {
            if (! Schema::hasColumn('process_maps', 'description')) {
                $table->text('description')->nullable()->after('name_en');
            }

            if (! Schema::hasColumn('process_maps', 'brand_color')) {
                $table->char('brand_color', 7)->nullable()->after('description');
            }

            if (! Schema::hasColumn('process_maps', 'logo_url')) {
                $table->string('logo_url', 500)->nullable()->after('brand_color');
            }

            if (! Schema::hasColumn('process_maps', 'node_styles')) {
                $table->json('node_styles')->nullable()->after('logo_url');
            }

            if (! Schema::hasColumn('process_maps', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('node_styles');
            }
        });
    }

    public function down(): void
    {
        Schema::table('process_maps', function (Blueprint $table) {
            foreach (['description', 'brand_color', 'logo_url', 'node_styles', 'is_active'] as $column) {
                if (Schema::hasColumn('process_maps', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

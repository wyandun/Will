<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an optional, single (non-bilingual) text description to the three
     * process-hierarchy levels: processes, sub_processes, and sub_sub_processes.
     * Mirrors the existing process_maps.description column.
     */
    public function up(): void
    {
        foreach (['processes', 'sub_processes', 'sub_sub_processes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'description')) {
                    $table->text('description')->nullable()->after('name_en');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['processes', 'sub_processes', 'sub_sub_processes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'description')) {
                    $table->dropColumn('description');
                }
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Process categories — groups of processes within a process map.
     * Standard BPMN classification: strategic, value_chain (core operations), support.
     */
    public function up(): void
    {
        Schema::create('process_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('process_map_id')
                ->constrained('process_maps')
                ->cascadeOnDelete();

            $table->string('type', 20)->comment('strategic | value_chain | support');

            $table->string('name_es');
            $table->string('name_en');

            // Controls display order within the process map
            $table->unsignedSmallInteger('order_index')->default(0);

            $table->timestamps();

            $table->index(['process_map_id', 'type']);
            $table->index(['process_map_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_categories');
    }
};

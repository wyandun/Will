<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Processes — top-level process groups within a category.
     * code is a short identifier, e.g. 'GTH', 'SC', 'MKT'.
     * Used as prefix for sub-process codes (GTH-P01, GTH-P02, etc.).
     */
    public function up(): void
    {
        Schema::create('processes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('process_categories')
                ->cascadeOnDelete();

            // Short code used as prefix for sub-process codes
            $table->string('code', 20)->comment('e.g. GTH, SC, MKT');

            $table->string('name_es');
            $table->string('name_en');

            $table->unsignedSmallInteger('order_index')->default(0);

            $table->timestamps();

            $table->index(['category_id', 'order_index']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};

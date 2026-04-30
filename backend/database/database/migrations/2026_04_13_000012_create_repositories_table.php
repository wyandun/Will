<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Document repositories — one per company (or one per sub-franchise within a company).
     *
     * If sub_franchise_id is null → this is the company-level repository.
     * If sub_franchise_id is set  → this repository belongs to that sub-franchise.
     */
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Null = company-level repository; set for sub-franchise repositories
            $table->foreignId('sub_franchise_id')
                ->nullable()
                ->constrained('franchises')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('company_id');
            $table->index('sub_franchise_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};

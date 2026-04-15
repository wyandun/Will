<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Companies table — represents Small Business clients.
     * sm_franchise_id links to the SM franchise that manages this SB.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('state', 50)->nullable();
            $table->string('country', 50)->default('USA');

            // Which SM franchise manages this company
            $table->foreignId('sm_franchise_id')
                ->constrained('franchises')
                ->restrictOnDelete();

            $table->unsignedInteger('employees_count')->nullable();
            $table->decimal('annual_revenue', 15, 2)->nullable();
            $table->unsignedSmallInteger('years_operating')->nullable();

            $table->string('logo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();

            $table->timestamps();

            $table->index('sm_franchise_id');
            $table->index('industry');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

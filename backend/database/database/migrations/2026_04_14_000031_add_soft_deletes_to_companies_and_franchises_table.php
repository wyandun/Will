<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add soft-delete support to companies and franchises so that
     * accidental deletes are recoverable.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('franchises', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('franchises', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

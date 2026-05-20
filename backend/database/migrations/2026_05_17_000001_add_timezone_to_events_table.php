<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add timezone column and set color default for events table.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'timezone')) {
                $table->string('timezone', 100)->default('America/New_York')->after('all_day');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('color', 10)->nullable(false)->default('#3B82F6')->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('color', 10)->nullable()->default(null)->change();
        });
    }
};

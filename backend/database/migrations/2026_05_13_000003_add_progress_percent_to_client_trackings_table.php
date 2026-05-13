<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('client_trackings', 'progress_percent')) {
            return;
        }

        Schema::table('client_trackings', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('actual_end');
        });
    }

    public function down(): void
    {
        Schema::table('client_trackings', function (Blueprint $table) {
            $table->dropColumn('progress_percent');
        });
    }
};

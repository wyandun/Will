<?php

// backend/database/migrations/2026_05_01_000001_add_is_active_to_franchises_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('franchises', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('franchises', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};

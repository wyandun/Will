<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add logo_url to franchises for branding display in the portal header
     * and franchise profile pages.
     */
    public function up(): void
    {
        Schema::table('franchises', function (Blueprint $table) {
            if (! Schema::hasColumn('franchises', 'logo_url')) {
                $table->string('logo_url', 500)->nullable()->after('owner_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('franchises', function (Blueprint $table) {
            if (Schema::hasColumn('franchises', 'logo_url')) {
                $table->dropColumn('logo_url');
            }
        });
    }
};

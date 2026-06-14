<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_processes', function (Blueprint $table): void {
            $table->jsonb('node_links')->nullable()->after('walkthrough_en');
        });

        Schema::table('sub_sub_processes', function (Blueprint $table): void {
            $table->jsonb('node_links')->nullable()->after('walkthrough_en');
        });
    }

    public function down(): void
    {
        Schema::table('sub_processes', function (Blueprint $table): void {
            $table->dropColumn('node_links');
        });

        Schema::table('sub_sub_processes', function (Blueprint $table): void {
            $table->dropColumn('node_links');
        });
    }
};

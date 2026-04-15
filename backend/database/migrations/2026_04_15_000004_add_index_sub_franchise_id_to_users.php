<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a simple index on users.sub_franchise_id.
     *
     * Queries that filter or join by sub_franchise role (sub_franchise_owner,
     * sub_franchise_admin) would hit a sequential scan without this index.
     * The column already exists and has a deferred FK (migration 000004),
     * but no B-tree index was added at creation time.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('sub_franchise_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['sub_franchise_id']);
        });
    }
};

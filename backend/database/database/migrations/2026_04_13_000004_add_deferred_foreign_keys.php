<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the deferred foreign keys that could not be set in the original
     * table migrations due to circular dependencies:
     *
     *   users.sm_franchise_id  → franchises.id
     *   users.company_id       → companies.id
     *   users.sub_franchise_id → franchises.id
     *   franchises.owner_user_id    → users.id
     *   franchises.parent_company_id → companies.id
     *
     * All three tables now exist, so the constraints can be applied safely.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('sm_franchise_id')
                ->references('id')->on('franchises')
                ->nullOnDelete();

            $table->foreign('company_id')
                ->references('id')->on('companies')
                ->nullOnDelete();

            $table->foreign('sub_franchise_id')
                ->references('id')->on('franchises')
                ->nullOnDelete();
        });

        Schema::table('franchises', function (Blueprint $table) {
            $table->foreign('owner_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->foreign('parent_company_id')
                ->references('id')->on('companies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('franchises', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropForeign(['parent_company_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sm_franchise_id']);
            $table->dropForeign(['company_id']);
            $table->dropForeign(['sub_franchise_id']);
        });
    }
};

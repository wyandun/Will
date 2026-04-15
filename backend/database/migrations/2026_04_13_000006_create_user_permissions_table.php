<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Granular module permissions per user.
     * Replaces the old JSON blob in users.permissions.
     *
     * One row per user per module. The unique constraint prevents duplicates.
     * Modules: feed, contracts, repository, processes, accounting,
     *          inventory, tracking, catalog, calendar
     */
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Module enum values match the portal module list from CLAUDE.md
            $table->string('module', 30)->comment(
                'feed | contracts | repository | processes | accounting | inventory | tracking | catalog | calendar'
            );

            $table->boolean('can_read')->default(false);
            $table->boolean('can_write')->default(false);

            $table->timestamps();

            // One row per user per module
            $table->unique(['user_id', 'module']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};

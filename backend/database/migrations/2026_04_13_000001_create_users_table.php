<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Users table — created first with no FKs to franchises/companies.
     * Circular dependencies (users→franchises→companies→users) are resolved
     * in migration 000004_add_deferred_foreign_keys.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();

            // These FKs are nullable and added in a later migration
            // to break the circular dependency: users → franchises → companies → users
            $table->unsignedBigInteger('sm_franchise_id')->nullable();  // set for admin_sm role
            $table->unsignedBigInteger('company_id')->nullable();        // set for sb_owner / sb_employee
            $table->unsignedBigInteger('sub_franchise_id')->nullable();  // set for sub_franchise roles

            $table->string('avatar_path')->nullable();
            $table->string('job_title')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('bio')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index('email');
            $table->index('company_id');
            $table->index('sm_franchise_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BB Applications — public form submissions from prospective Business Bishops.
     *
     * The token field enables the public form to resume a draft application
     * without authentication. Once reviewed and approved, converted_user_id
     * is populated when the BB user account is created.
     */
    public function up(): void
    {
        Schema::create('bb_applications', function (Blueprint $table) {
            $table->id();

            $table->string('full_name', 200);
            $table->string('email', 200);
            $table->string('phone', 30)->nullable();

            $table->decimal('investment_range_min', 15, 2)->nullable();
            $table->decimal('investment_range_max', 15, 2)->nullable();
            $table->string('net_worth_range', 50)->nullable();

            $table->text('investor_experience')->nullable();
            $table->json('industries_of_interest')->nullable();
            $table->string('preferred_stage', 50)->nullable();
            $table->string('availability_type', 30)->nullable();
            $table->smallInteger('hours_per_month')->nullable();
            $table->text('business_background')->nullable();

            // Public token to resume the form without authentication
            $table->string('token', 100)->unique();

            $table->string('status', 20)->default('draft')->comment(
                'draft | submitted | reviewed | approved | rejected'
            );

            // Populated when a BB user account is created from this application
            $table->foreignId('converted_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('email');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_applications');
    }
};

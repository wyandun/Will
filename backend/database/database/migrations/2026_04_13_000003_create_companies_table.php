<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Companies table — represents Small Business clients.
     * sm_franchise_id links to the SM franchise that manages this SB.
     *
     * QBO fields store the OAuth2 tokens for QuickBooks Online integration.
     * Tokens are encrypted at rest via Laravel's Crypt facade (see Company model).
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('industry', 120)->nullable();
            $table->string('state', 60)->nullable();
            $table->string('country', 60)->default('USA');

            // Which SM franchise manages this company
            $table->foreignId('sm_franchise_id')
                ->constrained('franchises')
                ->restrictOnDelete();

            $table->unsignedInteger('employees_count')->nullable();
            $table->decimal('annual_revenue', 15, 2)->nullable();
            $table->unsignedSmallInteger('years_operating')->nullable();

            $table->string('logo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website', 180)->nullable();

            // QuickBooks Online OAuth2 integration
            $table->string('qbo_realm_id', 80)->nullable()->comment('QBO company ID');
            $table->text('qbo_access_token')->nullable()->comment('Encrypted via Crypt::encryptString');
            $table->text('qbo_refresh_token')->nullable()->comment('Encrypted');
            $table->timestamp('qbo_token_expires_at')->nullable();

            $table->timestamps();

            $table->index('sm_franchise_id');
            $table->index('industry');
            $table->index('qbo_realm_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

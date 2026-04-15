<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POS connections — OAuth tokens for Point of Sale integrations.
     *
     * Tokens are stored as TEXT since they can be long (JWT or opaque).
     * access_token and refresh_token must be encrypted at rest using
     * Laravel's encrypted cast on the model.
     *
     * One active connection per provider per company is expected,
     * but not enforced at DB level to allow token rotation flows.
     */
    public function up(): void
    {
        Schema::create('pos_connections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('provider', 30)->comment('square | stripe | shopify | clover | woocommerce');

            // Encrypted on the model via $casts = ['access_token' => 'encrypted']
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['company_id', 'provider']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_connections');
    }
};

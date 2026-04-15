<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Franchises table — holds both SM franchises (type='sm') and
     * sub-franchises opened by SB owners (type='sub').
     *
     * owner_user_id FK and parent_company_id FK are added in migration 000004
     * to avoid circular dependency (franchises → users → franchises).
     */
    public function up(): void
    {
        Schema::create('franchises', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // 'sm' = Strategic Mates regional office; 'sub' = opened by an SB owner
            $table->string('type', 10)->comment('sm | sub');

            // For sub-franchises: the SB company that owns this sub-franchise.
            // Null for SM franchises. FK added in migration 000004.
            $table->unsignedBigInteger('parent_company_id')->nullable();

            // The user who owns/manages this franchise. FK added in migration 000004.
            $table->unsignedBigInteger('owner_user_id')->nullable();

            $table->string('region')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();

            $table->timestamps();

            $table->index('type');
            $table->index('parent_company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franchises');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chart of accounts — standard double-entry bookkeeping account tree.
     *
     * Hierarchical structure via parent_id self-reference.
     * code is hierarchical: '1' → '1.1' → '1.1.1' etc.
     *
     * is_system=true marks accounts seeded by SM that cannot be deleted.
     * Company-specific accounts have is_system=false.
     */
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Hierarchical code: '1', '1.1', '1.1.1', etc.
            $table->string('code', 20)->comment('Hierarchical account code');

            $table->string('name');

            $table->string('type', 20)->comment('asset | liability | equity | revenue | expense');

            // Self-referential FK for account hierarchy
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();

            // SM-seeded accounts cannot be deleted
            $table->boolean('is_system')->default(false)
                ->comment('True = seeded by SM, cannot be deleted');

            $table->timestamps();

            // Unique code per company
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};

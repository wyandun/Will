<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventory items — products and materials tracked per company or sub-franchise.
     *
     * account_id links to chart_of_accounts so that inventory movements
     * automatically generate journal entries (via InventoryMovementService).
     *
     * When sub_franchise_id is set, the item belongs to that sub-franchise's stock.
     * When null, the item belongs to the parent company's stock.
     */
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Null = company-level stock; set for sub-franchise-specific items
            $table->foreignId('sub_franchise_id')
                ->nullable()
                ->constrained('franchises')
                ->nullOnDelete();

            $table->string('sku', 60)->nullable();
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('unit', 20)->comment('unit | kg | liter | box | hour | meter | other');

            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('sell_price', 15, 2)->default(0);

            $table->decimal('current_stock', 10, 2)->default(0);
            $table->decimal('min_stock', 10, 2)->default(0)
                ->comment('Alert threshold — used for low-stock notifications');

            // Links to the GL account for automatic journal entry generation on movement
            $table->foreignId('account_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'sub_franchise_id']);
            $table->index(['company_id', 'sku']);
            $table->unique(['company_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};

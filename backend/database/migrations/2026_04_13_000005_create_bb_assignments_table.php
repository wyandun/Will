<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business Bishop assignments — links a BB user to the company they sponsor.
     * Each company has exactly one BB (enforced by unique constraint on company_id).
     * BB has read-only access to accounting and contracts of that company only.
     */
    public function up(): void
    {
        Schema::create('bb_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bb_user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('The user with the bb role who sponsors this company');

            // One company can have only one active BB
            $table->foreignId('company_id')
                ->unique()
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->timestamp('assigned_at');

            $table->foreignId('assigned_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('The admin_sm or superadmin who made the assignment');

            $table->timestamps();

            $table->index('bb_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_assignments');
    }
};

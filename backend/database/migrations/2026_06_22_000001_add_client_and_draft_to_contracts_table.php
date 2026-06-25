<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add client + internal-draft fields to contracts:
     *   - client_user_id: the User (sb_owner / bb_employee) the contract is for.
     *       company_id is derived from this user; franchise from company.sm_franchise_id.
     *   - draft_url: optional link to an internal draft / file (pre-DocuSeal).
     *
     * Additive + nullable so existing rows are preserved.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'client_user_id')) {
                $table->foreignId('client_user_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->index('client_user_id');
            }

            if (! Schema::hasColumn('contracts', 'draft_url')) {
                $table->string('draft_url')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'client_user_id')) {
                $table->dropForeign(['client_user_id']);
                $table->dropColumn('client_user_id');
            }

            if (Schema::hasColumn('contracts', 'draft_url')) {
                $table->dropColumn('draft_url');
            }
        });
    }
};

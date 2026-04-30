<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns to contracts table:
     *   - certificate_url: URL to the DocuSeal signing certificate (proof of signature)
     *   - signers: JSON payload describing the three signers (elaborated_by, reviewed_by, approved_by)
     *             stored for DocuSeal webhook reconciliation
     *   - sent_at: timestamp when the contract was sent for signature
     *   - expires_at: contract expiration date for SLA tracking
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'certificate_url')) {
                $table->string('certificate_url', 500)->nullable()->after('signed_document_url');
            }

            if (! Schema::hasColumn('contracts', 'signers')) {
                $table->json('signers')->nullable()->after('certificate_url');
            }

            if (! Schema::hasColumn('contracts', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('signed_at');
            }

            if (! Schema::hasColumn('contracts', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            foreach (['certificate_url', 'signers', 'sent_at', 'expires_at'] as $column) {
                if (Schema::hasColumn('contracts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends process_documents for the richer document card:
 *  - dual-language uploaded files (ES = file_url/file_name already + EN variants)
 *  - reviewed_by / approved_by (franchise users) with timestamps
 *  - valid_from and notes.
 *
 * file_url now holds an internally stored (public disk) URL, not an external link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_documents', function (Blueprint $table) {
            $table->string('file_name', 255)->nullable()->after('file_url');
            $table->string('file_url_en', 500)->nullable()->after('file_name');
            $table->string('file_name_en', 255)->nullable()->after('file_url_en');

            $table->foreignId('reviewed_by')->nullable()->after('uploaded_by')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('approved_by');
            $table->timestamp('approved_at')->nullable()->after('reviewed_at');

            $table->date('valid_from')->nullable()->after('approved_at');
            $table->text('notes')->nullable()->after('valid_from');
        });
    }

    public function down(): void
    {
        Schema::table('process_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'file_name', 'file_url_en', 'file_name_en',
                'reviewed_at', 'approved_at', 'valid_from', 'notes',
            ]);
        });
    }
};

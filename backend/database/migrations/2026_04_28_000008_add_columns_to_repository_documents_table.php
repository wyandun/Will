<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns to repository_documents table:
     *   - setup_category: specific sub-category within the setup section
     *                     (e.g. 'legal', 'hr', 'certificates', 'marketing', 'sops')
     *   - code: document reference code (e.g. 'LEG-001') for tracking
     *   - file_url: CDN/storage URL for the file (complements file_path)
     */
    public function up(): void
    {
        Schema::table('repository_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('repository_documents', 'setup_category')) {
                $table->string('setup_category', 40)->nullable()->after('section');
            }

            if (!Schema::hasColumn('repository_documents', 'code')) {
                $table->string('code', 40)->nullable()->after('process_code');
            }

            if (!Schema::hasColumn('repository_documents', 'file_url')) {
                $table->string('file_url', 500)->nullable()->after('file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('repository_documents', function (Blueprint $table) {
            foreach (['setup_category', 'code', 'file_url'] as $column) {
                if (Schema::hasColumn('repository_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

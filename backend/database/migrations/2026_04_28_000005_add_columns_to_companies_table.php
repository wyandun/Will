<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns to companies table:
     *   - tax_id: EIN or tax identification number
     *   - zip_code: postal code for the company address
     *   - logo_url: CDN/storage URL for the company logo (complements logo_path)
     *   - primary_color: brand hex color (e.g. #FF5733)
     *   - secondary_color: secondary brand hex color
     *   - status: lifecycle status — active, inactive, suspended
     *   - converted_from_assessment_id: FK to assessments (populated via Close Deal)
     *
     * All columns are guarded with hasColumn() for idempotency.
     * converted_from_assessment_id is nullable — most companies won't have an assessment.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'tax_id')) {
                $table->string('tax_id', 50)->nullable()->after('name');
            }

            if (!Schema::hasColumn('companies', 'zip_code')) {
                $table->string('zip_code', 20)->nullable()->after('city');
            }

            if (!Schema::hasColumn('companies', 'logo_url')) {
                $table->string('logo_url', 500)->nullable()->after('logo_path');
            }

            if (!Schema::hasColumn('companies', 'primary_color')) {
                $table->char('primary_color', 7)->nullable()->after('logo_url');
            }

            if (!Schema::hasColumn('companies', 'secondary_color')) {
                $table->char('secondary_color', 7)->nullable()->after('primary_color');
            }

            if (!Schema::hasColumn('companies', 'status')) {
                $table->string('status', 30)->default('active')->after('secondary_color');
            }

            if (!Schema::hasColumn('companies', 'converted_from_assessment_id')) {
                // No constrained() here — assessments table is created in 000002,
                // but we add the FK as a plain unsignedBigInteger to avoid
                // circular dependency issues. A proper FK can be added separately if needed.
                $table->unsignedBigInteger('converted_from_assessment_id')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $columns = [
                'converted_from_assessment_id',
                'status',
                'secondary_color',
                'primary_color',
                'logo_url',
                'zip_code',
                'tax_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

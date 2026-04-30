<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add structured contact fields to assessment_contacts.
     *
     * The original migration stores data in stage-specific JSONB columns.
     * The reference SQL adds dedicated columns for company info, contact info,
     * and metadata — these allow direct SQL queries and indexing without JSONB extraction.
     *
     * Columns added (only if not already present):
     *   Company info: company_name, company_industry, company_phone, company_email,
     *                 company_address, company_state, company_zip, years_operating,
     *                 employees_count, annual_revenue
     *   Contact info: contact_name, contact_title, contact_phone, contact_email
     *   Preferences:  preferred_lang, best_time
     *   Misc:         notes, token (unique public token for form resumption)
     *
     * converted_company_id, reviewed_by, and decision_id already exist
     * from previous migrations.
     */
    public function up(): void
    {
        Schema::table('assessment_contacts', function (Blueprint $table) {
            // Company info
            if (! Schema::hasColumn('assessment_contacts', 'company_name')) {
                $table->string('company_name', 200)->nullable()->after('type');
            }

            if (! Schema::hasColumn('assessment_contacts', 'company_industry')) {
                $table->string('company_industry', 100)->nullable()->after('company_name');
            }

            if (! Schema::hasColumn('assessment_contacts', 'company_phone')) {
                $table->string('company_phone', 30)->nullable()->after('company_industry');
            }

            if (! Schema::hasColumn('assessment_contacts', 'company_email')) {
                $table->string('company_email', 200)->nullable()->after('company_phone');
            }

            if (! Schema::hasColumn('assessment_contacts', 'company_address')) {
                $table->string('company_address', 300)->nullable()->after('company_email');
            }

            if (! Schema::hasColumn('assessment_contacts', 'company_state')) {
                $table->string('company_state', 80)->nullable()->after('company_address');
            }

            if (! Schema::hasColumn('assessment_contacts', 'company_zip')) {
                $table->string('company_zip', 20)->nullable()->after('company_state');
            }

            if (! Schema::hasColumn('assessment_contacts', 'years_operating')) {
                $table->smallInteger('years_operating')->unsigned()->nullable()->after('company_zip');
            }

            if (! Schema::hasColumn('assessment_contacts', 'employees_count')) {
                $table->smallInteger('employees_count')->unsigned()->nullable()->after('years_operating');
            }

            if (! Schema::hasColumn('assessment_contacts', 'annual_revenue')) {
                // Stored as varchar in the reference SQL to allow ranges like '500k-1M'
                $table->string('annual_revenue', 50)->nullable()->after('employees_count');
            }

            // Contact person info
            if (! Schema::hasColumn('assessment_contacts', 'contact_name')) {
                $table->string('contact_name', 200)->nullable()->after('annual_revenue');
            }

            if (! Schema::hasColumn('assessment_contacts', 'contact_title')) {
                $table->string('contact_title', 100)->nullable()->after('contact_name');
            }

            if (! Schema::hasColumn('assessment_contacts', 'contact_phone')) {
                $table->string('contact_phone', 30)->nullable()->after('contact_title');
            }

            if (! Schema::hasColumn('assessment_contacts', 'contact_email')) {
                $table->string('contact_email', 200)->nullable()->after('contact_phone');
            }

            // Preferences and metadata
            if (! Schema::hasColumn('assessment_contacts', 'preferred_lang')) {
                $table->char('preferred_lang', 2)->default('es')->after('contact_email');
            }

            if (! Schema::hasColumn('assessment_contacts', 'best_time')) {
                $table->string('best_time', 100)->nullable()->after('preferred_lang');
            }

            if (! Schema::hasColumn('assessment_contacts', 'notes')) {
                $table->text('notes')->nullable()->after('best_time');
            }

            // Public token to resume the form without authentication
            if (! Schema::hasColumn('assessment_contacts', 'token')) {
                $table->string('token', 100)->unique()->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessment_contacts', function (Blueprint $table) {
            $columns = [
                'token',
                'notes',
                'best_time',
                'preferred_lang',
                'contact_email',
                'contact_phone',
                'contact_title',
                'contact_name',
                'annual_revenue',
                'employees_count',
                'years_operating',
                'company_zip',
                'company_state',
                'company_address',
                'company_email',
                'company_phone',
                'company_industry',
                'company_name',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('assessment_contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

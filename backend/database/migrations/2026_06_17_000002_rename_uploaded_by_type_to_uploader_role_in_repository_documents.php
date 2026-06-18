<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repository_documents', function ($table) {
            $table->renameColumn('uploaded_by_type', 'uploader_role');
        });
    }

    public function down(): void
    {
        Schema::table('repository_documents', function ($table) {
            $table->renameColumn('uploader_role', 'uploaded_by_type');
        });
    }
};

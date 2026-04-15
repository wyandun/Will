<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('state');
            $table->string('email')->nullable()->after('phone');
            $table->text('notes')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['city', 'email', 'notes']);
        });
    }
};

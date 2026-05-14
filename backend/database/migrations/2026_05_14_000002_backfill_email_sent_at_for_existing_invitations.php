<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rows that still have invitation_token but email_sent_at was never set
        // (they pre-date the email tracking feature). Backfill with created_at so
        // they don't trigger the "email not sent" warning in the UI.
        DB::table('users')
            ->whereNotNull('invitation_token')
            ->whereNull('invitation_accepted_at')
            ->whereNull('deleted_at')
            ->whereNull('email_sent_at')
            ->update(['email_sent_at' => DB::raw('created_at')]);
    }

    public function down(): void {}
};

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeedMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_seen_at_column_exists_on_users_table(): void
    {
        $this->assertTrue(
            Schema::hasColumn('users', 'last_seen_at'),
            'Column last_seen_at should exist on users table after migration runs'
        );
    }

    public function test_last_seen_at_column_is_nullable(): void
    {
        // Verify that inserting a user without last_seen_at does not throw
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'migration-test@example.com',
            'password' => bcrypt('password'),
            'last_seen_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNull(
            DB::table('users')->where('id', $userId)->value('last_seen_at')
        );
    }
}

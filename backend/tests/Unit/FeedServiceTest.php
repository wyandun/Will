<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Post;
use App\Models\PostInteraction;
use App\Models\User;
use App\Services\FeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeedService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeedService;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createFranchise(string $name = 'Test Franchise'): int
    {
        return DB::table('franchises')->insertGetId([
            'name' => $name,
            'type' => 'sm',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCompany(int $franchiseId, string $name = 'Test Company'): int
    {
        return DB::table('companies')->insertGetId([
            'name' => $name,
            'sm_franchise_id' => $franchiseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // getPosts — response structure (items + meta)
    // -------------------------------------------------------------------------

    public function test_get_posts_returns_items_and_meta_keys(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->create(['author_id' => $user->id]);

        $result = $this->service->getPosts($user);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('current_page', $result['meta']);
        $this->assertArrayHasKey('last_page', $result['meta']);
        $this->assertArrayHasKey('per_page', $result['meta']);
        $this->assertArrayHasKey('total', $result['meta']);
    }

    public function test_get_posts_returns_expected_keys_per_item(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->create([
            'author_id' => $user->id,
            'title' => 'Test Post',
            'body' => 'Body text.',
        ]);

        $result = $this->service->getPosts($user);
        $item = $result['items'][0];

        $expectedKeys = [
            'id', 'title', 'body', 'type', 'is_pinned',
            'image_url', 'file_url', 'file_name',
            'author_name', 'author_avatar',
            'likes_count', 'comments_count', 'created_at',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $item, "Missing key: $key");
        }
    }

    // -------------------------------------------------------------------------
    // getPosts — visibility scoping
    // -------------------------------------------------------------------------

    public function test_superadmin_sees_global_franchise_and_company_posts(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $franchiseId = $this->createFranchise();

        Post::factory()->create(['visibility' => 'global', 'author_id' => $superadmin->id]);
        Post::factory()->forFranchise($franchiseId)->create(['author_id' => $superadmin->id]);
        Post::factory()->create(['visibility' => 'company', 'author_id' => $superadmin->id]);

        $result = $this->service->getPosts($superadmin);

        $this->assertCount(3, $result['items']);
        $this->assertEquals(3, $result['meta']['total']);
    }

    public function test_admin_sm_sees_global_posts_and_own_franchise_posts(): void
    {
        $franchiseId = $this->createFranchise();
        $otherFranchiseId = $this->createFranchise('Other Franchise');

        $admin = User::factory()->create(['sm_franchise_id' => $franchiseId]);
        $admin->assignRole('admin_sm');

        $author = User::factory()->create();

        Post::factory()->create(['visibility' => 'global', 'author_id' => $author->id]);
        Post::factory()->forFranchise($franchiseId)->create(['author_id' => $author->id]);
        Post::factory()->forFranchise($otherFranchiseId)->create(['author_id' => $author->id]);

        $result = $this->service->getPosts($admin);

        $this->assertCount(2, $result['items']);
    }

    public function test_sb_owner_sees_global_and_own_franchise_posts_only(): void
    {
        $franchiseId = $this->createFranchise();
        $otherFranchiseId = $this->createFranchise('Other Franchise');

        $owner = User::factory()->create(['sm_franchise_id' => $franchiseId]);
        $owner->assignRole('sb_owner');

        $author = User::factory()->create();

        Post::factory()->create(['visibility' => 'global', 'author_id' => $author->id]);
        Post::factory()->forFranchise($franchiseId)->create(['author_id' => $author->id]);
        Post::factory()->forFranchise($otherFranchiseId)->create(['author_id' => $author->id]);

        $result = $this->service->getPosts($owner);

        $this->assertCount(2, $result['items']);
    }

    // -------------------------------------------------------------------------
    // getPosts — published_at filtering
    // -------------------------------------------------------------------------

    public function test_future_published_at_posts_do_not_appear_in_feed(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->create(['author_id' => $user->id, 'published_at' => null]);
        Post::factory()->scheduledFuture()->create(['author_id' => $user->id]);

        $result = $this->service->getPosts($user);

        $this->assertCount(1, $result['items']);
    }

    public function test_post_with_past_published_at_appears_in_feed(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->published()->create(['author_id' => $user->id]);

        $result = $this->service->getPosts($user);

        $this->assertCount(1, $result['items']);
    }

    public function test_post_with_null_published_at_appears_immediately(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->create(['author_id' => $user->id, 'published_at' => null]);

        $result = $this->service->getPosts($user);

        $this->assertCount(1, $result['items']);
    }

    // -------------------------------------------------------------------------
    // getPosts — soft deleted posts
    // -------------------------------------------------------------------------

    public function test_soft_deleted_posts_do_not_appear_in_feed(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $post = Post::factory()->create(['author_id' => $user->id]);
        $post->delete();

        $result = $this->service->getPosts($user);

        $this->assertCount(0, $result['items']);
        $this->assertEquals(0, $result['meta']['total']);
    }

    // -------------------------------------------------------------------------
    // getPosts — ordering (pinned first)
    // -------------------------------------------------------------------------

    public function test_pinned_posts_appear_before_unpinned_posts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->create(['author_id' => $user->id, 'is_pinned' => false]);
        Post::factory()->pinned()->create(['author_id' => $user->id]);
        Post::factory()->create(['author_id' => $user->id, 'is_pinned' => false]);

        $result = $this->service->getPosts($user);

        $this->assertTrue((bool) $result['items'][0]['is_pinned']);
        $this->assertFalse((bool) $result['items'][1]['is_pinned']);
        $this->assertFalse((bool) $result['items'][2]['is_pinned']);
    }

    // -------------------------------------------------------------------------
    // getPosts — interaction counts
    // -------------------------------------------------------------------------

    public function test_get_posts_returns_correct_likes_and_comments_counts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $post = Post::factory()->create(['author_id' => $user->id]);

        PostInteraction::factory()->count(3)->create(['post_id' => $post->id, 'type' => 'like']);
        PostInteraction::factory()->count(2)->create(['post_id' => $post->id, 'type' => 'comment', 'content' => 'comment text']);

        $result = $this->service->getPosts($user);

        $this->assertEquals(3, $result['items'][0]['likes_count']);
        $this->assertEquals(2, $result['items'][0]['comments_count']);
    }

    public function test_get_posts_returns_zero_counts_when_no_interactions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->create(['author_id' => $user->id]);

        $result = $this->service->getPosts($user);

        $this->assertEquals(0, $result['items'][0]['likes_count']);
        $this->assertEquals(0, $result['items'][0]['comments_count']);
    }

    // -------------------------------------------------------------------------
    // getPosts — pagination
    // -------------------------------------------------------------------------

    public function test_default_page_returns_10_results_when_more_than_10_exist(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->count(15)->create(['author_id' => $user->id]);

        $result = $this->service->getPosts($user);

        $this->assertCount(10, $result['items']);
        $this->assertEquals(10, $result['meta']['per_page']);
        $this->assertEquals(15, $result['meta']['total']);
        $this->assertEquals(2, $result['meta']['last_page']);
    }

    public function test_page_2_returns_remaining_results(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->count(15)->create(['author_id' => $user->id]);

        $result = $this->service->getPosts($user, null, 2, 10);

        $this->assertCount(5, $result['items']);
        $this->assertEquals(2, $result['meta']['current_page']);
        $this->assertEquals(2, $result['meta']['last_page']);
    }

    public function test_custom_per_page_is_respected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->count(12)->create(['author_id' => $user->id]);

        $result = $this->service->getPosts($user, null, 1, 5);

        $this->assertCount(5, $result['items']);
        $this->assertEquals(5, $result['meta']['per_page']);
        $this->assertEquals(3, $result['meta']['last_page']);
        $this->assertEquals(12, $result['meta']['total']);
    }

    public function test_get_posts_returns_empty_items_when_no_posts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $result = $this->service->getPosts($user);

        $this->assertIsArray($result['items']);
        $this->assertCount(0, $result['items']);
        $this->assertEquals(0, $result['meta']['total']);
        $this->assertEquals(1, $result['meta']['last_page']);
    }

    public function test_meta_current_page_reflects_requested_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->count(25)->create(['author_id' => $user->id]);

        $result = $this->service->getPosts($user, null, 3, 10);

        $this->assertEquals(3, $result['meta']['current_page']);
    }

    // -------------------------------------------------------------------------
    // getPosts — search (ILIKE — PostgreSQL only; skipped on SQLite)
    // -------------------------------------------------------------------------

    public function test_search_filters_by_title(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('ILIKE search requires PostgreSQL.');
        }

        $user = User::factory()->create(['name' => 'Regular Author']);
        $user->assignRole('superadmin');

        Post::factory()->create(['author_id' => $user->id, 'title' => 'Quarterly Revenue Report']);
        Post::factory()->create(['author_id' => $user->id, 'title' => 'Team Announcement']);

        $result = $this->service->getPosts($user, 'quarterly');

        $this->assertCount(1, $result['items']);
        $this->assertStringContainsStringIgnoringCase('quarterly', $result['items'][0]['title']);
    }

    public function test_search_filters_by_body(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('ILIKE search requires PostgreSQL.');
        }

        $user = User::factory()->create(['name' => 'Regular Author']);
        $user->assignRole('superadmin');

        Post::factory()->create([
            'author_id' => $user->id,
            'title' => 'Generic Title',
            'body' => 'This post discusses franchise expansion strategy.',
        ]);
        Post::factory()->create([
            'author_id' => $user->id,
            'title' => 'Another Post',
            'body' => 'Unrelated content here.',
        ]);

        $result = $this->service->getPosts($user, 'franchise expansion');

        $this->assertCount(1, $result['items']);
    }

    public function test_search_filters_by_author_name(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('ILIKE search requires PostgreSQL.');
        }

        $authorA = User::factory()->create(['name' => 'Maria Rodriguez']);
        $authorB = User::factory()->create(['name' => 'John Smith']);

        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->create(['author_id' => $authorA->id]);
        Post::factory()->create(['author_id' => $authorB->id]);

        $result = $this->service->getPosts($user, 'Rodriguez');

        $this->assertCount(1, $result['items']);
        $this->assertEquals('Maria Rodriguez', $result['items'][0]['author_name']);
    }

    public function test_null_search_returns_all_visible_posts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->count(3)->create(['author_id' => $user->id]);

        $result = $this->service->getPosts($user, null);

        $this->assertCount(3, $result['items']);
    }

    // -------------------------------------------------------------------------
    // getPresence — online / recently active
    // -------------------------------------------------------------------------

    public function test_user_seen_within_5_minutes_is_online(): void
    {
        $franchiseId = $this->createFranchise();
        $companyId = $this->createCompany($franchiseId);

        $viewer = User::factory()->create(['company_id' => $companyId]);
        $viewer->assignRole('sb_owner');

        DB::table('users')->where('id', $viewer->id)->update([
            'last_seen_at' => now()->subMinutes(2),
        ]);

        $result = $this->service->getPresence($viewer);

        $onlineIds = array_column($result['online'], 'id');
        $this->assertContains($viewer->id, $onlineIds);
    }

    public function test_user_seen_more_than_5_minutes_ago_is_recently_active(): void
    {
        $franchiseId = $this->createFranchise();
        $companyId = $this->createCompany($franchiseId);

        $viewer = User::factory()->create(['company_id' => $companyId]);
        $viewer->assignRole('sb_owner');

        DB::table('users')->where('id', $viewer->id)->update([
            'last_seen_at' => now()->subMinutes(10),
        ]);

        $result = $this->service->getPresence($viewer);

        $recentIds = array_column($result['recently_active'], 'id');
        $this->assertContains($viewer->id, $recentIds);

        $onlineIds = array_column($result['online'], 'id');
        $this->assertNotContains($viewer->id, $onlineIds);
    }

    public function test_user_with_null_last_seen_at_does_not_appear_in_either_panel(): void
    {
        $franchiseId = $this->createFranchise();
        $companyId = $this->createCompany($franchiseId);

        $viewer = User::factory()->create(['company_id' => $companyId]);
        $viewer->assignRole('sb_owner');

        DB::table('users')->where('id', $viewer->id)->update(['last_seen_at' => null]);

        $result = $this->service->getPresence($viewer);

        $onlineIds = array_column($result['online'], 'id');
        $recentIds = array_column($result['recently_active'], 'id');

        $this->assertNotContains($viewer->id, $onlineIds);
        $this->assertNotContains($viewer->id, $recentIds);
    }

    public function test_is_current_user_flag_is_true_only_for_authenticated_user(): void
    {
        $franchiseId = $this->createFranchise();
        $companyId = $this->createCompany($franchiseId);

        $viewer = User::factory()->create(['company_id' => $companyId]);
        $viewer->assignRole('sb_owner');

        $peer = User::factory()->create(['company_id' => $companyId]);
        $peer->assignRole('sb_employee');

        DB::table('users')->whereIn('id', [$viewer->id, $peer->id])->update([
            'last_seen_at' => now()->subMinutes(2),
        ]);

        $result = $this->service->getPresence($viewer);

        $allUsers = array_merge($result['online'], $result['recently_active']);

        foreach ($allUsers as $entry) {
            if ($entry['id'] === $viewer->id) {
                $this->assertTrue($entry['is_current_user'], 'Viewer should have is_current_user=true');
            } else {
                $this->assertFalse($entry['is_current_user'], "User {$entry['id']} should have is_current_user=false");
            }
        }
    }

    public function test_recently_active_list_is_limited_to_10_users(): void
    {
        $franchiseId = $this->createFranchise();
        $companyId = $this->createCompany($franchiseId);

        $viewer = User::factory()->create(['company_id' => $companyId]);
        $viewer->assignRole('sb_owner');

        $peers = User::factory()->count(15)->create(['company_id' => $companyId]);
        $peers->each(fn ($u) => $u->assignRole('sb_employee'));

        DB::table('users')
            ->whereIn('id', $peers->pluck('id'))
            ->update(['last_seen_at' => now()->subMinutes(10)]);

        $result = $this->service->getPresence($viewer);

        $this->assertLessThanOrEqual(10, count($result['recently_active']));
    }

    public function test_recently_active_users_are_not_duplicated_in_online_list(): void
    {
        $franchiseId = $this->createFranchise();
        $companyId = $this->createCompany($franchiseId);

        $viewer = User::factory()->create(['company_id' => $companyId]);
        $viewer->assignRole('sb_owner');

        $peer = User::factory()->create(['company_id' => $companyId]);
        $peer->assignRole('sb_employee');

        DB::table('users')->where('id', $peer->id)->update([
            'last_seen_at' => now()->subMinutes(10),
        ]);

        $result = $this->service->getPresence($viewer);

        $onlineIds = array_column($result['online'], 'id');
        $this->assertNotContains($peer->id, $onlineIds);
    }

    // -------------------------------------------------------------------------
    // getPresence — visibility scoping
    // -------------------------------------------------------------------------

    public function test_superadmin_sees_all_users_in_presence(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $franchiseId = $this->createFranchise();
        $companyId = $this->createCompany($franchiseId);

        $peer = User::factory()->create(['company_id' => $companyId]);
        $peer->assignRole('sb_owner');

        DB::table('users')->where('id', $peer->id)->update([
            'last_seen_at' => now()->subMinutes(1),
        ]);

        $result = $this->service->getPresence($superadmin);

        $onlineIds = array_column($result['online'], 'id');
        $this->assertContains($peer->id, $onlineIds);
    }

    public function test_admin_sm_sees_only_users_of_own_franchise(): void
    {
        $franchiseId = $this->createFranchise();
        $otherFranchiseId = $this->createFranchise('Other');

        $admin = User::factory()->create(['sm_franchise_id' => $franchiseId]);
        $admin->assignRole('admin_sm');

        $sameFranchiseUser = User::factory()->create(['sm_franchise_id' => $franchiseId]);
        $sameFranchiseUser->assignRole('sb_owner');

        $otherFranchiseUser = User::factory()->create(['sm_franchise_id' => $otherFranchiseId]);
        $otherFranchiseUser->assignRole('sb_owner');

        DB::table('users')
            ->whereIn('id', [$sameFranchiseUser->id, $otherFranchiseUser->id])
            ->update(['last_seen_at' => now()->subMinutes(1)]);

        $result = $this->service->getPresence($admin);

        $onlineIds = array_column($result['online'], 'id');
        $this->assertContains($sameFranchiseUser->id, $onlineIds);
        $this->assertNotContains($otherFranchiseUser->id, $onlineIds);
    }

    public function test_sb_owner_sees_only_users_of_own_company(): void
    {
        $franchiseId = $this->createFranchise();
        $myCompanyId = $this->createCompany($franchiseId, 'My Company');
        $otherCompanyId = $this->createCompany($franchiseId, 'Other Company');

        $owner = User::factory()->create(['company_id' => $myCompanyId]);
        $owner->assignRole('sb_owner');

        $colleague = User::factory()->create(['company_id' => $myCompanyId]);
        $colleague->assignRole('sb_employee');

        $outsider = User::factory()->create(['company_id' => $otherCompanyId]);
        $outsider->assignRole('sb_employee');

        DB::table('users')
            ->whereIn('id', [$colleague->id, $outsider->id])
            ->update(['last_seen_at' => now()->subMinutes(1)]);

        $result = $this->service->getPresence($owner);

        $onlineIds = array_column($result['online'], 'id');
        $this->assertContains($colleague->id, $onlineIds);
        $this->assertNotContains($outsider->id, $onlineIds);
    }

    // -------------------------------------------------------------------------
    // getPresence — response structure
    // -------------------------------------------------------------------------

    public function test_get_presence_returns_online_and_recently_active_keys(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $result = $this->service->getPresence($user);

        $this->assertArrayHasKey('online', $result);
        $this->assertArrayHasKey('recently_active', $result);
        $this->assertIsArray($result['online']);
        $this->assertIsArray($result['recently_active']);
    }
}

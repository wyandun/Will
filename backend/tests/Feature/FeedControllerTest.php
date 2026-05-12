<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\FeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // GET /api/v1/feed/posts
    // -------------------------------------------------------------------------

    public function test_unauthenticated_user_gets_401_on_posts(): void
    {
        $response = $this->getJson('/api/v1/feed/posts');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_gets_200_with_success_envelope_on_posts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sb_owner');

        $response = $this->actingAs($user)->getJson('/api/v1/feed/posts');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['success', 'data']);
    }

    public function test_posts_response_contains_items_and_meta(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sb_owner');

        Post::factory()->count(3)->create([
            'author_id' => $user->id,
            'visibility' => 'global',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/feed/posts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'items',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ],
        ]);

        $this->assertIsArray($response->json('data.items'));
        $this->assertCount(3, $response->json('data.items'));
        $this->assertEquals(3, $response->json('data.meta.total'));
        $this->assertEquals(1, $response->json('data.meta.current_page'));
    }

    public function test_posts_endpoint_paginates_to_page_2(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Post::factory()->count(15)->create([
            'author_id' => $user->id,
            'visibility' => 'global',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/feed/posts?page=2&per_page=10');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.meta.current_page'));
        $this->assertCount(5, $response->json('data.items'));
    }

    public function test_posts_endpoint_clamps_per_page_to_maximum(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $response = $this->actingAs($user)->getJson('/api/v1/feed/posts?per_page=999');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(50, $response->json('data.meta.per_page'));
    }

    public function test_posts_endpoint_passes_search_term_to_service(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sb_owner');

        $mock = $this->mock(FeedService::class);
        $mock->expects('getPosts')
            ->once()
            ->withArgs(fn (User $u, ?string $search, int $page, int $perPage) => $u->id === $user->id && $search === 'quarterly' && $page === 1 && $perPage === 10
            )
            ->andReturn(['items' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 0]]);

        $response = $this->actingAs($user)->getJson('/api/v1/feed/posts?search=quarterly');

        $response->assertStatus(200);
    }

    public function test_posts_endpoint_passes_null_search_when_param_omitted(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sb_owner');

        $mock = $this->mock(FeedService::class);
        $mock->expects('getPosts')
            ->once()
            ->withArgs(fn (User $u, ?string $search, int $page, int $perPage) => $u->id === $user->id && $search === null
            )
            ->andReturn(['items' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 0]]);

        $response = $this->actingAs($user)->getJson('/api/v1/feed/posts');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/feed/presence
    // -------------------------------------------------------------------------

    public function test_unauthenticated_user_gets_401_on_presence(): void
    {
        $response = $this->getJson('/api/v1/feed/presence');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_gets_200_with_success_envelope_on_presence(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sb_owner');

        $response = $this->actingAs($user)->getJson('/api/v1/feed/presence');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_presence_endpoint_returns_online_and_recently_active_keys(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sb_owner');

        $response = $this->actingAs($user)->getJson('/api/v1/feed/presence');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'online',
                'recently_active',
            ],
        ]);
        $this->assertIsArray($response->json('data.online'));
        $this->assertIsArray($response->json('data.recently_active'));
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/feed/posts
    // -------------------------------------------------------------------------

    public function test_superadmin_can_create_post_and_gets_201(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $response = $this->actingAs($user)->postJson('/api/v1/feed/posts', [
            'title' => 'New Announcement',
            'body' => 'This is the body.',
            'type' => 'announcement',
            'visibility' => 'global',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['success', 'data' => ['post']]);
    }

    public function test_admin_sm_can_create_post_and_gets_201(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin_sm');

        $response = $this->actingAs($user)->postJson('/api/v1/feed/posts', [
            'title' => 'Franchise News',
            'body' => 'Body content.',
            'type' => 'news',
            'visibility' => 'franchise',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }

    public function test_sb_owner_cannot_create_post_and_gets_403(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sb_owner');

        $response = $this->actingAs($user)->postJson('/api/v1/feed/posts', [
            'title' => 'My Post',
            'body' => 'Body.',
            'type' => 'news',
            'visibility' => 'global',
        ]);

        $response->assertStatus(403);
    }

    public function test_create_post_fails_validation_when_required_fields_missing(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $response = $this->actingAs($user)->postJson('/api/v1/feed/posts', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'body', 'type', 'visibility']);
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/feed/posts/{id}
    // -------------------------------------------------------------------------

    public function test_author_can_update_own_post_and_gets_200(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin_sm');

        $post = Post::factory()->create([
            'author_id' => $user->id,
            'title' => 'Original',
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/feed/posts/{$post->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_update_nonexistent_post_returns_404(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $response = $this->actingAs($user)->putJson('/api/v1/feed/posts/99999', ['title' => 'Updated']);

        $response->assertStatus(404);
    }

    public function test_other_user_cannot_update_post_and_gets_403(): void
    {
        $author = User::factory()->create();
        $author->assignRole('admin_sm');

        $other = User::factory()->create();
        $other->assignRole('sb_owner');

        $post = Post::factory()->create(['author_id' => $author->id]);

        $response = $this->actingAs($other)->putJson("/api/v1/feed/posts/{$post->id}", [
            'title' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/feed/posts/{id}
    // -------------------------------------------------------------------------

    public function test_author_can_delete_own_post_and_gets_200(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin_sm');

        $post = Post::factory()->create(['author_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/feed/posts/{$post->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_superadmin_can_delete_any_post_and_gets_200(): void
    {
        $author = User::factory()->create();
        $author->assignRole('admin_sm');

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $post = Post::factory()->create(['author_id' => $author->id]);

        $response = $this->actingAs($superadmin)->deleteJson("/api/v1/feed/posts/{$post->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_delete_nonexistent_post_returns_404(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $response = $this->actingAs($user)->deleteJson('/api/v1/feed/posts/99999');

        $response->assertStatus(404);
    }
}

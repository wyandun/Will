<?php

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

    public function test_posts_endpoint_returns_array_under_data_key(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sb_owner');

        Post::factory()->count(3)->create([
            'author_id' => $user->id,
            'visibility' => 'global',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/feed/posts');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
        $this->assertCount(3, $response->json('data'));
    }

    public function test_posts_endpoint_passes_search_term_to_service(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sb_owner');

        $mock = $this->mock(FeedService::class);
        $mock->expects('getPosts')
            ->once()
            ->withArgs(fn (User $u, ?string $search) => $u->id === $user->id && $search === 'quarterly')
            ->andReturn([]);

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
            ->withArgs(fn (User $u, ?string $search) => $u->id === $user->id && $search === null)
            ->andReturn([]);

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
}

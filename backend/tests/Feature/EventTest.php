<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Event;
use App\Models\Franchise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function createSuperadmin(): User
    {
        SpatieRole::firstOrCreate(['name' => Role::SUPERADMIN, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole(Role::SUPERADMIN);

        return $user;
    }

    private function createUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    private function createAdminSm(?int $franchiseId = null): User
    {
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);

        $franchise = $franchiseId ? Franchise::find($franchiseId) : Franchise::factory()->create();
        $user = User::factory()->create(['sm_franchise_id' => $franchise->id]);
        $user->assignRole(Role::ADMIN_SM);

        return $user;
    }

    private function validEventData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Team Standup',
            'description' => 'Daily standup meeting',
            'location' => 'Conference Room A',
            'start_at' => '2026-06-01 09:00:00',
            'end_at' => '2026-06-01 10:00:00',
            'all_day' => false,
            'timezone' => 'America/New_York',
            'color' => '#3B82F6',
        ], $overrides);
    }

    // ===========================================================================
    // Authentication
    // ===========================================================================

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/v1/events')->assertStatus(401);
        $this->postJson('/api/v1/events')->assertStatus(401);
    }

    // ===========================================================================
    // GET /api/v1/events (index)
    // ===========================================================================

    public function test_authenticated_user_can_list_events(): void
    {
        $user = $this->createUser();
        Event::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_event_index_returns_correct_json_structure(): void
    {
        $user = $this->createUser();
        Event::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'description',
                    'location',
                    'start_at',
                    'end_at',
                    'all_day',
                    'timezone',
                    'color',
                    'visibility',
                    'type',
                    'created_by',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    // ===========================================================================
    // POST /api/v1/events (store)
    // ===========================================================================

    public function test_admin_sm_with_franchise_can_create_event(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.title', 'Team Standup');
        $response->assertJsonPath('data.created_by.id', $user->id);

        $this->assertDatabaseHas('events', [
            'title' => 'Team Standup',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_without_role_cannot_create_event(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData());

        $response->assertStatus(403);
    }

    public function test_admin_sm_without_franchise_cannot_create_event(): void
    {
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);

        $user = User::factory()->create(['sm_franchise_id' => null]);
        $user->assignRole(Role::ADMIN_SM);

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData());

        $response->assertStatus(403);
    }

    public function test_superadmin_can_create_event(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/events', $this->validEventData());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }

    public function test_event_requires_title(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson(
            '/api/v1/events',
            $this->validEventData(['title' => ''])
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
    }

    public function test_event_date_validation_end_before_start(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'start_at' => '2026-06-01 10:00:00',
            'end_at' => '2026-06-01 09:00:00',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_at');
    }

    public function test_event_validates_timezone(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'timezone' => 'Invalid/Timezone',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('timezone');
    }

    public function test_event_validates_color_in_allowed_list(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'color' => '#000000',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('color');
    }

    public function test_all_day_normalizes_times_to_midnight(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'all_day' => true,
            'start_at' => '2026-06-01 14:30:00',
            'end_at' => '2026-06-01 16:30:00',
        ]));

        $response->assertStatus(201);

        $event = Event::latest('id')->first();
        $this->assertEquals('00:00:00', $event->start_at->format('H:i:s'));
        $this->assertEquals('00:00:00', $event->end_at->format('H:i:s'));
    }

    // ===========================================================================
    // GET /api/v1/events/{id} (show)
    // ===========================================================================

    public function test_authenticated_user_can_view_any_event(): void
    {
        $user = $this->createUser();
        $event = Event::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $event->id);
    }

    // ===========================================================================
    // PUT /api/v1/events/{id} (update)
    // ===========================================================================

    public function test_creator_can_update_own_event(): void
    {
        $user = $this->createUser();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_non_creator_cannot_update_event(): void
    {
        $creator = $this->createUser();
        $otherUser = $this->createUser();
        $event = Event::factory()->create(['user_id' => $creator->id]);

        $response = $this->actingAs($otherUser)->putJson("/api/v1/events/{$event->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_update_any_event(): void
    {
        $superadmin = $this->createSuperadmin();
        $event = Event::factory()->create();

        $response = $this->actingAs($superadmin)->putJson("/api/v1/events/{$event->id}", [
            'title' => 'Admin Updated',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Admin Updated');
    }

    // ===========================================================================
    // DELETE /api/v1/events/{id} (destroy)
    // ===========================================================================

    public function test_creator_can_delete_own_event(): void
    {
        $user = $this->createUser();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Verify soft delete
        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_non_creator_cannot_delete_event(): void
    {
        $creator = $this->createUser();
        $otherUser = $this->createUser();
        $event = Event::factory()->create(['user_id' => $creator->id]);

        $response = $this->actingAs($otherUser)->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(403);
    }

    public function test_superadmin_can_delete_any_event(): void
    {
        $superadmin = $this->createSuperadmin();
        $event = Event::factory()->create();

        $response = $this->actingAs($superadmin)->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    // ===========================================================================
    // Visibility Scoping
    // ===========================================================================

    public function test_user_can_see_public_events_in_index(): void
    {
        $user = $this->createUser();
        Event::factory()->create(['visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_see_private_events_of_others(): void
    {
        $user = $this->createUser();
        Event::factory()->create(['visibility' => 'private']);

        $response = $this->actingAs($user)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_user_can_see_own_private_events(): void
    {
        $user = $this->createUser();
        Event::factory()->create(['visibility' => 'private', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_user_can_see_franchise_events_in_same_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $creator = $this->createUser(['sm_franchise_id' => $franchise->id]);
        $viewer = $this->createUser(['sm_franchise_id' => $franchise->id]);
        Event::factory()->create(['visibility' => 'franchise', 'user_id' => $creator->id]);

        $response = $this->actingAs($viewer)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_see_franchise_events_from_different_franchise(): void
    {
        $franchiseA = Franchise::factory()->create();
        $franchiseB = Franchise::factory()->create();
        $creator = $this->createUser(['sm_franchise_id' => $franchiseA->id]);
        $viewer = $this->createUser(['sm_franchise_id' => $franchiseB->id]);
        Event::factory()->create(['visibility' => 'franchise', 'user_id' => $creator->id]);

        $response = $this->actingAs($viewer)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_superadmin_sees_all_events_regardless_of_visibility(): void
    {
        $superadmin = $this->createSuperadmin();
        Event::factory()->create(['visibility' => 'public']);
        Event::factory()->create(['visibility' => 'private']);
        Event::factory()->create(['visibility' => 'franchise']);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_show_private_event_returns_403_for_non_owner(): void
    {
        $viewer = $this->createUser();
        $event = Event::factory()->create(['visibility' => 'private']);

        $response = $this->actingAs($viewer)->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(403);
    }

    public function test_show_franchise_event_visible_to_same_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $creator = $this->createUser(['sm_franchise_id' => $franchise->id]);
        $viewer = $this->createUser(['sm_franchise_id' => $franchise->id]);
        $event = Event::factory()->create(['visibility' => 'franchise', 'user_id' => $creator->id]);

        $response = $this->actingAs($viewer)->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $event->id);
    }

    // ===========================================================================
    // Bug fix: PATCH end_at without start_at
    // ===========================================================================

    public function test_update_only_end_at_with_valid_date_succeeds(): void
    {
        $user = $this->createUser();
        $event = Event::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-06-01 09:00:00',
            'end_at' => '2026-06-01 10:00:00',
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'end_at' => '2026-06-01 12:00:00',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.end_at', fn ($v) => str_contains($v, '2026-06-01'));
    }

    public function test_update_only_end_at_before_existing_start_at_fails(): void
    {
        $user = $this->createUser();
        $event = Event::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-06-01 09:00:00',
            'end_at' => '2026-06-01 10:00:00',
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'end_at' => '2026-05-31 08:00:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_at');
    }

    // ===========================================================================
    // Bug fix: visibility and type validation
    // ===========================================================================

    public function test_create_event_with_visibility_private(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'visibility' => 'private',
        ]));

        $response->assertStatus(201);
        $response->assertJsonPath('data.visibility', 'private');
    }

    public function test_create_event_with_invalid_visibility_fails(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'visibility' => 'secret',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('visibility');
    }

    public function test_create_event_with_valid_type(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'type' => 'meeting',
        ]));

        $response->assertStatus(201);
        $response->assertJsonPath('data.type', 'meeting');
    }

    public function test_create_event_with_invalid_type_fails(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'type' => 'party',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');
    }

    public function test_event_response_includes_visibility(): void
    {
        $user = $this->createUser();
        Event::factory()->create(['visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.visibility', 'public');
    }

    public function test_default_visibility_is_private_when_not_specified(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData());

        $response->assertStatus(201);
        $response->assertJsonPath('data.visibility', 'private');
    }
}

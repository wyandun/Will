<?php

namespace Tests\Feature;

use App\Enums\EventColor;
use App\Enums\Role;
use App\Models\Event;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
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
        UserPermission::syncForRole($user->id, Role::SUPERADMIN);

        return $user;
    }

    private function createUser(array $attrs = []): User
    {
        SpatieRole::firstOrCreate(['name' => Role::SB_OWNER, 'guard_name' => 'web']);

        $user = User::factory()->create($attrs);
        $user->assignRole(Role::SB_OWNER);
        UserPermission::syncForRole($user->id, Role::SB_OWNER);

        return $user;
    }

    private function createAdminSm(?int $franchiseId = null): User
    {
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);

        $franchise = $franchiseId ? Franchise::find($franchiseId) : Franchise::factory()->create();
        $user = User::factory()->create(['sm_franchise_id' => $franchise->id]);
        $user->assignRole(Role::ADMIN_SM);
        UserPermission::syncForRole($user->id, Role::ADMIN_SM);

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
    // GET /api/v1/events — Search & Filter
    // ===========================================================================

    public function test_index_search_filters_by_title(): void
    {
        $user = $this->createUser();
        Event::factory()->create(['title' => 'Alpha Meeting', 'visibility' => 'public']);
        Event::factory()->create(['title' => 'Beta Workshop', 'visibility' => 'public']);
        Event::factory()->create(['title' => 'Gamma Session', 'visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/v1/events?search=Alpha');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Alpha Meeting');
    }

    public function test_index_search_filters_by_description(): void
    {
        $user = $this->createUser();
        Event::factory()->create(['description' => 'Quarterly planning session', 'visibility' => 'public']);
        Event::factory()->create(['description' => 'Weekly sync meeting', 'visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/v1/events?search=quarterly');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_search_filters_by_location(): void
    {
        $user = $this->createUser();
        Event::factory()->create(['location' => 'Main Conference Room', 'visibility' => 'public']);
        Event::factory()->create(['location' => 'Remote via Zoom', 'visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/v1/events?search=conference');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_search_is_case_insensitive(): void
    {
        $user = $this->createUser();
        Event::factory()->create(['title' => 'Alpha Meeting', 'visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/v1/events?search=alpha+meeting');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_start_from_filters_events(): void
    {
        $user = $this->createUser();
        // Event ending before the filter date — should NOT appear
        Event::factory()->create([
            'visibility' => 'public',
            'start_at' => '2026-01-10 09:00:00',
            'end_at' => '2026-01-10 10:00:00',
        ]);
        // Event ending after the filter date — should appear
        Event::factory()->create([
            'visibility' => 'public',
            'start_at' => '2026-06-15 09:00:00',
            'end_at' => '2026-06-15 10:00:00',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/events?start_from=2026-06-01T00:00:00.000Z');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_end_before_filters_events(): void
    {
        $user = $this->createUser();
        // Event starting before the filter date — should appear
        Event::factory()->create([
            'visibility' => 'public',
            'start_at' => '2026-03-01 09:00:00',
            'end_at' => '2026-03-01 10:00:00',
        ]);
        // Event starting after the filter date — should NOT appear
        Event::factory()->create([
            'visibility' => 'public',
            'start_at' => '2026-08-01 09:00:00',
            'end_at' => '2026-08-01 10:00:00',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/events?end_before=2026-06-01T00:00:00.000Z');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_combined_search_and_date_range(): void
    {
        $user = $this->createUser();
        // Matches title but outside date range
        Event::factory()->create([
            'title' => 'Strategy Review',
            'visibility' => 'public',
            'start_at' => '2026-01-10 09:00:00',
            'end_at' => '2026-01-10 10:00:00',
        ]);
        // Inside date range but title doesn't match
        Event::factory()->create([
            'title' => 'Unrelated Event',
            'visibility' => 'public',
            'start_at' => '2026-06-15 09:00:00',
            'end_at' => '2026-06-15 10:00:00',
        ]);
        // Matches both title and date range
        Event::factory()->create([
            'title' => 'Strategy Review',
            'visibility' => 'public',
            'start_at' => '2026-06-15 09:00:00',
            'end_at' => '2026-06-15 10:00:00',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/events?search=Strategy&start_from=2026-06-01T00:00:00.000Z');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    // ===========================================================================
    // GET /api/v1/events — Pagination
    // ===========================================================================

    public function test_index_default_pagination_is_10(): void
    {
        $user = $this->createUser();
        Event::factory()->count(15)->create(['visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 15);
    }

    public function test_index_custom_per_page(): void
    {
        $user = $this->createUser();
        Event::factory()->count(8)->create(['visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/v1/events?per_page=5');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    public function test_index_page_navigation(): void
    {
        $user = $this->createUser();
        Event::factory()->count(15)->create(['visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/v1/events?page=2');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.current_page', 2);
    }

    public function test_index_meta_structure(): void
    {
        $user = $this->createUser();
        Event::factory()->count(3)->create(['visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_index_per_page_clamped_to_min_5(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/events?per_page=1');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');
    }

    public function test_index_per_page_clamped_to_max_200(): void
    {
        $user = $this->createUser();
        Event::factory()->count(3)->create(['visibility' => 'public']);

        $response = $this->actingAs($user)->getJson('/api/v1/events?per_page=999');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');
    }

    public function test_index_per_page_below_min_returns_422(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/events?per_page=1');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');
    }

    // ===========================================================================
    // GET /api/v1/events — Query param validation
    // ===========================================================================

    public function test_index_invalid_start_from_returns_422(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/events?start_from=not-a-date');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_from');
    }

    public function test_index_invalid_end_before_returns_422(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/events?end_before=garbage');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_before');
    }

    public function test_index_search_max_length_validated(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/events?search='.str_repeat('a', 101));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('search');
    }

    public function test_index_valid_search_within_max_length_succeeds(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/events?search='.str_repeat('a', 100));

        $response->assertStatus(200);
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
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson(
            '/api/v1/events',
            $this->validEventData(['title' => ''])
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
    }

    public function test_event_date_validation_end_before_start(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'start_at' => '2026-06-01 10:00:00',
            'end_at' => '2026-06-01 09:00:00',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_at');
    }

    public function test_event_validates_timezone(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'timezone' => 'Invalid/Timezone',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('timezone');
    }

    public function test_event_validates_color_in_allowed_list(): void
    {
        $user = $this->createAdminSm();

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
    // POST /api/v1/events — Store validation gaps
    // ===========================================================================

    public function test_store_requires_start_at(): void
    {
        $user = $this->createAdminSm();
        $data = $this->validEventData();
        unset($data['start_at']);

        $response = $this->actingAs($user)->postJson('/api/v1/events', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_at');
    }

    public function test_store_requires_end_at(): void
    {
        $user = $this->createAdminSm();
        $data = $this->validEventData();
        unset($data['end_at']);

        $response = $this->actingAs($user)->postJson('/api/v1/events', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_at');
    }

    public function test_store_start_at_must_be_valid_date(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'start_at' => 'not-a-date',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_at');
    }

    public function test_store_title_max_255(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'title' => str_repeat('a', 256),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
    }

    public function test_store_description_max_5000(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'description' => str_repeat('a', 5001),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('description');
    }

    public function test_store_location_max_255(): void
    {
        $user = $this->createAdminSm();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
            'location' => str_repeat('a', 256),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('location');
    }

    public function test_store_default_type_is_casual(): void
    {
        $user = $this->createAdminSm();
        $data = $this->validEventData();
        unset($data['type']);

        $response = $this->actingAs($user)->postJson('/api/v1/events', $data);

        $response->assertStatus(201);
        $response->assertJsonPath('data.type', 'casual');
    }

    // ===========================================================================
    // POST /api/v1/events — Event types coverage
    // ===========================================================================

    public function test_store_all_valid_event_types(): void
    {
        $user = $this->createAdminSm();

        foreach (['casual', 'meeting', 'deadline', 'reminder', 'training'] as $type) {
            $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
                'type' => $type,
            ]));

            $response->assertStatus(201);
            $response->assertJsonPath('data.type', $type);
        }
    }

    // ===========================================================================
    // POST /api/v1/events — Event colors coverage
    // ===========================================================================

    public function test_store_all_valid_event_colors(): void
    {
        $user = $this->createAdminSm();

        foreach (EventColor::values() as $color) {
            $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validEventData([
                'color' => $color,
            ]));

            $response->assertStatus(201);
            $response->assertJsonPath('data.color', $color);
        }
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

    public function test_show_returns_created_by_relationship(): void
    {
        $creator = $this->createUser();
        $event = Event::factory()->create(['user_id' => $creator->id, 'visibility' => 'public']);

        $response = $this->actingAs($creator)->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['created_by' => ['id', 'name']],
        ]);
        $response->assertJsonPath('data.created_by.id', $creator->id);
    }

    public function test_show_nonexistent_event_returns_404(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/events/99999');

        $response->assertStatus(404);
    }

    public function test_show_franchise_event_different_franchise_returns_403(): void
    {
        $franchiseA = Franchise::factory()->create();
        $franchiseB = Franchise::factory()->create();
        $creator = $this->createUser(['sm_franchise_id' => $franchiseA->id]);
        $viewer = $this->createUser(['sm_franchise_id' => $franchiseB->id]);
        $event = Event::factory()->create(['visibility' => 'franchise', 'user_id' => $creator->id]);

        $response = $this->actingAs($viewer)->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(403);
    }

    // ===========================================================================
    // PUT /api/v1/events/{id} (update)
    // ===========================================================================

    public function test_creator_can_update_own_event(): void
    {
        $user = $this->createAdminSm();
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

    public function test_update_partial_only_title(): void
    {
        $user = $this->createAdminSm();
        $event = Event::factory()->create([
            'user_id' => $user->id,
            'title' => 'Original Title',
            'description' => 'Original description',
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'title' => 'New Title',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'New Title');
        $response->assertJsonPath('data.description', 'Original description');
    }

    public function test_update_all_fields(): void
    {
        $user = $this->createAdminSm();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'location' => 'New Location',
            'start_at' => '2026-07-01 10:00:00',
            'end_at' => '2026-07-01 11:00:00',
            'all_day' => false,
            'timezone' => 'America/Los_Angeles',
            'color' => '#EF4444',
            'visibility' => 'public',
            'type' => 'meeting',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');
        $response->assertJsonPath('data.type', 'meeting');
        $response->assertJsonPath('data.visibility', 'public');
        $response->assertJsonPath('data.color', '#EF4444');
    }

    public function test_update_start_at_and_end_at_validates_order(): void
    {
        $user = $this->createAdminSm();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'start_at' => '2026-07-01 12:00:00',
            'end_at' => '2026-07-01 09:00:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_at');
    }

    // ===========================================================================
    // DELETE /api/v1/events/{id} (destroy)
    // ===========================================================================

    public function test_creator_can_delete_own_event(): void
    {
        $user = $this->createAdminSm();
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

    public function test_delete_nonexistent_event_returns_404(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->deleteJson('/api/v1/events/99999');

        $response->assertStatus(404);
    }

    public function test_deleted_event_not_returned_in_index(): void
    {
        $user = $this->createUser();
        $event = Event::factory()->create(['visibility' => 'public', 'user_id' => $user->id]);

        $event->delete();

        $response = $this->actingAs($user)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
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

    public function test_show_franchise_event_403_for_user_without_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $creator = $this->createUser(['sm_franchise_id' => $franchise->id]);
        $viewer = $this->createUser(['sm_franchise_id' => null]);
        $event = Event::factory()->create(['visibility' => 'franchise', 'user_id' => $creator->id]);

        $response = $this->actingAs($viewer)->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(403);
    }

    // ===========================================================================
    // Bug fix: PATCH end_at without start_at
    // ===========================================================================

    public function test_update_only_end_at_with_valid_date_succeeds(): void
    {
        $user = $this->createAdminSm();
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
        $user = $this->createAdminSm();
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

    // ===========================================================================
    // GET /api/v1/dashboard/events
    // ===========================================================================

    public function test_dashboard_events_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/dashboard/events')->assertStatus(401);
    }

    public function test_dashboard_events_returns_max_5(): void
    {
        $user = $this->createUser();
        Event::factory()->count(8)->create([
            'visibility' => 'public',
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(1)->addHour(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard/events');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_dashboard_events_only_returns_future_events(): void
    {
        $user = $this->createUser();
        // Past events — should NOT appear
        Event::factory()->count(2)->create([
            'visibility' => 'public',
            'start_at' => now()->subDays(5),
            'end_at' => now()->subDays(5)->addHour(),
        ]);
        // Future events — should appear
        Event::factory()->count(2)->create([
            'visibility' => 'public',
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(1)->addHour(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard/events');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_dashboard_events_respects_visibility(): void
    {
        $owner = $this->createUser();
        $viewer = $this->createUser();

        // Public event — viewer should see this
        Event::factory()->create([
            'visibility' => 'public',
            'user_id' => $owner->id,
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(1)->addHour(),
        ]);
        // Private event from someone else — viewer should NOT see this
        Event::factory()->create([
            'visibility' => 'private',
            'user_id' => $owner->id,
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(1)->addHour(),
        ]);

        $response = $this->actingAs($viewer)->getJson('/api/v1/dashboard/events');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_dashboard_events_returns_correct_structure(): void
    {
        $user = $this->createUser();
        Event::factory()->create([
            'visibility' => 'public',
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(1)->addHour(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard/events');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'start', 'end', 'all_day', 'color'],
            ],
        ]);
    }
}

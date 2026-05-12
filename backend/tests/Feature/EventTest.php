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

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::ADMIN_SM,
            Role::SB_OWNER,
            Role::SB_EMPLOYEE,
        ] as $role) {
            SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function createSuperadmin(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->assignRole(Role::SUPERADMIN);

        return $user;
    }

    private function createAdminSm(?Franchise $franchise = null, array $attrs = []): User
    {
        $franchise ??= Franchise::factory()->create();

        $user = User::factory()->create(array_merge([
            'sm_franchise_id' => $franchise->id,
        ], $attrs));
        $user->assignRole(Role::ADMIN_SM);

        return $user;
    }

    private function createSbOwner(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->assignRole(Role::SB_OWNER);

        return $user;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Team Standup',
            'description' => 'Daily standup meeting',
            'location' => 'Conference Room A',
            'start_at' => '2026-06-15 09:00:00',
            'end_at' => '2026-06-15 10:00:00',
            'timezone' => 'America/New_York',
            'all_day' => false,
            'color' => '#3B82F6',
            'visibility' => 'private',
            'type' => 'meeting',
        ], $overrides);
    }

    // ===========================================================================
    // GET /api/v1/events  (index)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_event_index(): void
    {
        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(401);
    }

    public function test_superadmin_sees_all_events(): void
    {
        $superadmin = $this->createSuperadmin();
        $other = User::factory()->create();

        Event::factory()->create(['user_id' => $superadmin->id]);
        Event::factory()->create(['user_id' => $other->id, 'visibility' => 'private']);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_sm_sees_own_and_franchise_and_public_events(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);
        $colleague = User::factory()->create(['sm_franchise_id' => $franchise->id]);
        $outsider = User::factory()->create();

        // Own event (private) — visible
        Event::factory()->create(['user_id' => $admin->id, 'visibility' => 'private']);
        // Colleague franchise event — visible
        Event::factory()->create(['user_id' => $colleague->id, 'visibility' => 'franchise']);
        // Outsider public event — visible
        Event::factory()->create(['user_id' => $outsider->id, 'visibility' => 'public']);
        // Outsider private event — NOT visible
        Event::factory()->create(['user_id' => $outsider->id, 'visibility' => 'private']);

        $response = $this->actingAs($admin)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_sb_owner_sees_own_and_public_events(): void
    {
        $owner = $this->createSbOwner();
        $other = User::factory()->create();

        Event::factory()->create(['user_id' => $owner->id, 'visibility' => 'private']);
        Event::factory()->create(['user_id' => $other->id, 'visibility' => 'public']);
        Event::factory()->create(['user_id' => $other->id, 'visibility' => 'private']);

        $response = $this->actingAs($owner)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_event_index_returns_correct_json_structure(): void
    {
        $user = $this->createSuperadmin();
        Event::factory()->create(['user_id' => $user->id]);

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
                    'timezone',
                    'all_day',
                    'color',
                    'visibility',
                    'type',
                    'user' => ['id', 'name'],
                    'created_at',
                ],
            ],
        ]);
    }

    // ===========================================================================
    // POST /api/v1/events  (store)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_event_store(): void
    {
        $response = $this->postJson('/api/v1/events', $this->validPayload());

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_event(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload());

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Team Standup');
        $response->assertJsonPath('data.description', 'Daily standup meeting');
        $response->assertJsonPath('data.location', 'Conference Room A');
        $response->assertJsonPath('data.timezone', 'America/New_York');
        $response->assertJsonPath('data.all_day', false);
        $response->assertJsonPath('data.color', '#3B82F6');
        $response->assertJsonPath('data.visibility', 'private');
        $response->assertJsonPath('data.type', 'meeting');

        $this->assertDatabaseHas('events', [
            'user_id' => $user->id,
            'title' => 'Team Standup',
            'timezone' => 'America/New_York',
            'color' => '#3B82F6',
            'visibility' => 'private',
        ]);
    }

    public function test_store_event_validates_required_title(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
            'title' => '',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_store_event_validates_required_start_at(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events',
            collect($this->validPayload())->except('start_at')->all()
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_at']);
    }

    public function test_store_event_validates_required_end_at(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events',
            collect($this->validPayload())->except('end_at')->all()
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end_at']);
    }

    public function test_store_event_validates_end_at_after_or_equal_start(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
            'start_at' => '2026-06-15 10:00:00',
            'end_at' => '2026-06-15 09:00:00',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end_at']);
    }

    public function test_store_event_validates_required_timezone(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events',
            collect($this->validPayload())->except('timezone')->all()
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['timezone']);
    }

    public function test_store_event_validates_visibility_enum(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
            'visibility' => 'invalid',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['visibility']);
    }

    public function test_store_event_validates_type_enum(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
            'type' => 'party',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_store_event_validates_color_max_length(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
            'color' => '#AABBCCDDEE11',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['color']);
    }

    public function test_store_event_with_all_day_true(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
            'all_day' => true,
            'start_at' => '2026-06-15',
            'end_at' => '2026-06-15',
        ]));

        $response->assertStatus(201);
        $response->assertJsonPath('data.all_day', true);
    }

    public function test_store_event_saves_all_visibility_values(): void
    {
        $user = $this->createSuperadmin();

        foreach (['private', 'franchise', 'public'] as $vis) {
            $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
                'visibility' => $vis,
            ]));
            $response->assertStatus(201);
            $response->assertJsonPath('data.visibility', $vis);
        }
    }

    public function test_store_event_saves_all_type_values(): void
    {
        $user = $this->createSuperadmin();

        foreach (['casual', 'meeting', 'deadline', 'reminder', 'training'] as $type) {
            $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
                'type' => $type,
            ]));
            $response->assertStatus(201);
            $response->assertJsonPath('data.type', $type);
        }
    }

    // ===========================================================================
    // GET /api/v1/events/{event}  (show)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_event_show(): void
    {
        $event = Event::factory()->create();

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_view_event(): void
    {
        $user = $this->createSuperadmin();
        $event = Event::factory()->create([
            'user_id' => $user->id,
            'title' => 'My Event',
            'timezone' => 'Europe/London',
            'color' => '#FF0000',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'My Event');
        $response->assertJsonPath('data.timezone', 'Europe/London');
        $response->assertJsonPath('data.color', '#FF0000');
        $response->assertJsonPath('data.user.id', $user->id);
    }

    // ===========================================================================
    // PUT/PATCH /api/v1/events/{event}  (update)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_event_update(): void
    {
        $event = Event::factory()->create();

        $response = $this->putJson("/api/v1/events/{$event->id}", ['title' => 'Updated']);

        $response->assertStatus(401);
    }

    public function test_owner_can_update_own_event(): void
    {
        $user = $this->createSbOwner();
        $event = Event::factory()->create(['user_id' => $user->id, 'title' => 'Original']);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'title' => 'Updated Title',
            'timezone' => 'Asia/Tokyo',
            'color' => '#00FF00',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');
        $response->assertJsonPath('data.timezone', 'Asia/Tokyo');
        $response->assertJsonPath('data.color', '#00FF00');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Updated Title',
            'timezone' => 'Asia/Tokyo',
        ]);
    }

    public function test_non_owner_cannot_update_event(): void
    {
        $owner = User::factory()->create();
        $other = $this->createSbOwner();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->putJson("/api/v1/events/{$event->id}", [
            'title' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_update_any_event(): void
    {
        $superadmin = $this->createSuperadmin();
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($superadmin)->putJson("/api/v1/events/{$event->id}", [
            'title' => 'Admin Updated',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Admin Updated');
    }

    public function test_update_validates_end_at_after_start(): void
    {
        $user = $this->createSuperadmin();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'start_at' => '2026-06-15 12:00:00',
            'end_at' => '2026-06-15 11:00:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end_at']);
    }

    public function test_update_validates_visibility_enum(): void
    {
        $user = $this->createSuperadmin();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'visibility' => 'secret',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['visibility']);
    }

    // ===========================================================================
    // DELETE /api/v1/events/{event}  (destroy)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_event_delete(): void
    {
        $event = Event::factory()->create();

        $response = $this->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(401);
    }

    public function test_owner_can_soft_delete_own_event(): void
    {
        $user = $this->createSbOwner();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_non_owner_cannot_delete_event(): void
    {
        $owner = User::factory()->create();
        $other = $this->createSbOwner();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(403);
    }

    public function test_superadmin_can_delete_any_event(): void
    {
        $superadmin = $this->createSuperadmin();
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($superadmin)->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_deleted_event_not_returned_in_index(): void
    {
        $user = $this->createSuperadmin();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $event->delete();

        $response = $this->actingAs($user)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    // ===========================================================================
    // Color & All Day — specific field behavior
    // ===========================================================================

    public function test_color_is_persisted_and_returned(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
            'color' => '#EF4444',
        ]));

        $response->assertStatus(201);
        $response->assertJsonPath('data.color', '#EF4444');

        $this->assertDatabaseHas('events', ['color' => '#EF4444']);
    }

    public function test_all_day_toggle_persists_correctly(): void
    {
        $user = $this->createSuperadmin();

        // Create as not all-day
        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
            'all_day' => false,
        ]));
        $response->assertStatus(201);
        $response->assertJsonPath('data.all_day', false);

        $eventId = $response->json('data.id');

        // Update to all-day
        $response = $this->actingAs($user)->putJson("/api/v1/events/{$eventId}", [
            'all_day' => true,
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.all_day', true);

        $this->assertDatabaseHas('events', ['id' => $eventId, 'all_day' => true]);
    }

    // ===========================================================================
    // Timezone — required and persisted
    // ===========================================================================

    public function test_timezone_is_required_on_create(): void
    {
        $user = $this->createSuperadmin();

        $payload = collect($this->validPayload())->except('timezone')->all();
        $response = $this->actingAs($user)->postJson('/api/v1/events', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['timezone']);
    }

    public function test_timezone_is_persisted_and_returned(): void
    {
        $user = $this->createSuperadmin();

        $response = $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
            'timezone' => 'Pacific/Auckland',
        ]));

        $response->assertStatus(201);
        $response->assertJsonPath('data.timezone', 'Pacific/Auckland');

        $this->assertDatabaseHas('events', ['timezone' => 'Pacific/Auckland']);
    }

    public function test_timezone_can_be_updated(): void
    {
        $user = $this->createSuperadmin();
        $event = Event::factory()->create([
            'user_id' => $user->id,
            'timezone' => 'America/New_York',
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'timezone' => 'Europe/Madrid',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.timezone', 'Europe/Madrid');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'timezone' => 'Europe/Madrid',
        ]);
    }
}

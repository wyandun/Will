<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Events\FranchiseStatusChanged;
use App\Models\Franchise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class FranchiseTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Create a user with the superadmin Spatie role.
     */
    private function createSuperadmin(array $attributes = []): User
    {
        SpatieRole::firstOrCreate(['name' => Role::SUPERADMIN, 'guard_name' => 'web']);

        $user = User::factory()->create($attributes);
        $user->assignRole(Role::SUPERADMIN);

        return $user;
    }

    /**
     * Create a user with the admin_sm Spatie role and link them to a franchise.
     */
    private function createAdminSm(Franchise $franchise, array $attributes = []): User
    {
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);

        $user = User::factory()->create(array_merge([
            'sm_franchise_id' => $franchise->id,
        ], $attributes));
        $user->assignRole(Role::ADMIN_SM);

        return $user;
    }

    /**
     * Create a user with no specific franchise role.
     */
    private function createRegularUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    // ===========================================================================
    // GET /api/v1/franchises  (index)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_franchise_index(): void
    {
        $response = $this->getJson('/api/v1/franchises');

        $response->assertStatus(401);
    }

    public function test_superadmin_can_list_all_franchises(): void
    {
        $superadmin = $this->createSuperadmin();
        Franchise::factory()->count(3)->create();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/franchises');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_franchise_index_returns_correct_json_structure(): void
    {
        $superadmin = $this->createSuperadmin();
        Franchise::factory()->create([
            'name' => 'Test Franchise',
            'email' => 'test@franchise.com',
            'country' => 'USA',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/franchises');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'type',
                    'email',
                    'country',
                    'is_active',
                    'admins_count',
                    'clients_count',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    public function test_franchise_index_returns_admins_and_clients_counts(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/franchises');

        $response->assertStatus(200);
        $data = $response->json('data.0');
        $this->assertArrayHasKey('admins_count', $data);
        $this->assertArrayHasKey('clients_count', $data);
    }

    public function test_admin_sm_sees_only_their_franchise(): void
    {
        $franchise = Franchise::factory()->create(['name' => 'My Franchise']);
        Franchise::factory()->create(['name' => 'Other Franchise']);

        $admin = $this->createAdminSm($franchise);

        $response = $this->actingAs($admin)->getJson('/api/v1/franchises');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'My Franchise');
    }

    public function test_user_without_role_is_forbidden_from_franchise_index(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/franchises');

        $response->assertStatus(403);
    }

    // ===========================================================================
    // POST /api/v1/franchises  (store)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_franchise_store(): void
    {
        $response = $this->postJson('/api/v1/franchises', [
            'name' => 'New Franchise',
            'type' => 'sm',
        ]);

        $response->assertStatus(401);
    }

    public function test_superadmin_can_create_franchise(): void
    {
        $superadmin = $this->createSuperadmin();

        $payload = [
            'name'     => 'New Franchise',
            'country'  => 'Canada',
            'timezone' => 'America/Toronto',
            'phone'    => '+1 416-555-0100',
            'address'  => '100 King St W, Toronto, ON',
        ];

        $response = $this->actingAs($superadmin)->postJson('/api/v1/franchises', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'New Franchise');
        $response->assertJsonPath('data.country', 'Canada');

        $this->assertDatabaseHas('franchises', [
            'name'    => 'New Franchise',
            'type'    => 'sm',
            'country' => 'Canada',
        ]);
    }

    public function test_franchise_is_active_by_default_when_created(): void
    {
        $superadmin = $this->createSuperadmin();

        $payload = [
            'name'     => 'Active By Default',
            'country'  => 'Mexico',
            'timezone' => 'America/Mexico_City',
            'phone'    => '+52 55 1234 5678',
            'address'  => 'Av. Reforma 123, Ciudad de México',
        ];

        $response = $this->actingAs($superadmin)->postJson('/api/v1/franchises', $payload);

        $response->assertStatus(201);

        // The DB default sets is_active = true even if not sent in the payload
        $this->assertDatabaseHas('franchises', [
            'name'      => 'Active By Default',
            'is_active' => true,
        ]);
    }

    public function test_store_franchise_validates_required_name(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/franchises', [
            'type' => 'sm',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_franchise_validates_type_must_be_sm_or_sub(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/franchises', [
            'name' => 'Some Franchise',
            'type' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_store_franchise_validates_required_country(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/franchises', [
            'name'     => 'Some Franchise',
            'timezone' => 'America/New_York',
            'phone'    => '+1 555-0100',
            'address'  => '123 Main St',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['country']);
    }

    public function test_store_franchise_validates_timezone_format(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/franchises', [
            'name' => 'Some Franchise',
            'type' => 'sm',
            'timezone' => 'Europe/Madriz',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['timezone']);
    }

    public function test_store_franchise_accepts_valid_timezone(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/franchises', [
            'name'     => 'Timezone Franchise',
            'country'  => 'United States',
            'timezone' => 'America/New_York',
            'phone'    => '+1 555-0100',
            'address'  => '123 Main St, New York, NY',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('franchises', [
            'name'     => 'Timezone Franchise',
            'timezone' => 'America/New_York',
        ]);
    }

    public function test_admin_sm_cannot_create_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);

        // Send a valid payload so FormRequest validation passes and the policy is reached.
        $response = $this->actingAs($admin)->postJson('/api/v1/franchises', [
            'name'     => 'Unauthorized',
            'country'  => 'Mexico',
            'timezone' => 'America/Mexico_City',
            'phone'    => '+52 55 1234 5678',
            'address'  => 'Calle Falsa 123',
        ]);

        $response->assertStatus(403);
    }

    // ===========================================================================
    // GET /api/v1/franchises/{franchise}  (show)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_franchise_show(): void
    {
        $franchise = Franchise::factory()->create();

        $response = $this->getJson("/api/v1/franchises/{$franchise->id}");

        $response->assertStatus(401);
    }

    public function test_superadmin_can_view_any_franchise(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create([
            'name' => 'Detail Franchise',
            'email' => 'detail@franchise.com',
            'country' => 'Mexico',
        ]);

        $response = $this->actingAs($superadmin)->getJson("/api/v1/franchises/{$franchise->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Detail Franchise');
        $response->assertJsonPath('data.email', 'detail@franchise.com');
        $response->assertJsonPath('data.country', 'Mexico');
    }

    public function test_admin_sm_can_view_their_own_franchise(): void
    {
        $franchise = Franchise::factory()->create(['name' => 'My Franchise']);
        $admin = $this->createAdminSm($franchise);

        $response = $this->actingAs($admin)->getJson("/api/v1/franchises/{$franchise->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'My Franchise');
    }

    public function test_admin_sm_cannot_view_another_franchise(): void
    {
        $myFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($myFranchise);

        $response = $this->actingAs($admin)->getJson("/api/v1/franchises/{$otherFranchise->id}");

        $response->assertStatus(403);
    }

    // ===========================================================================
    // PUT/PATCH /api/v1/franchises/{franchise}  (update)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_franchise_update(): void
    {
        $franchise = Franchise::factory()->create();

        $response = $this->putJson("/api/v1/franchises/{$franchise->id}", [
            'name' => 'Updated',
        ]);

        $response->assertStatus(401);
    }

    public function test_superadmin_can_update_franchise(): void
    {
        $superadmin  = $this->createSuperadmin();
        $franchise = Franchise::factory()->create(['name' => 'Original Name']);

        $payload = [
            'name'     => 'Updated Name',
            'country'  => 'Argentina',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'phone'    => '+54 11 5555-0100',
            'address'  => 'Av. Corrientes 1234, Buenos Aires',
        ];

        $response = $this->actingAs($superadmin)
            ->putJson("/api/v1/franchises/{$franchise->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.country', 'Argentina');

        $this->assertDatabaseHas('franchises', [
            'id'      => $franchise->id,
            'name'    => 'Updated Name',
            'country' => 'Argentina',
        ]);
    }

    public function test_update_franchise_validates_required_fields(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise  = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->putJson("/api/v1/franchises/{$franchise->id}", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'country', 'timezone', 'phone', 'address']);
    }

    public function test_update_franchise_validates_type_must_be_sm_or_sub(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->putJson("/api/v1/franchises/{$franchise->id}", [
                'type' => 'invalid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_update_franchise_validates_timezone_format(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->putJson("/api/v1/franchises/{$franchise->id}", [
                'timezone' => 'UTC+5',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['timezone']);
    }

    public function test_admin_sm_cannot_update_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $admin     = $this->createAdminSm($franchise);

        // Send a valid payload so FormRequest validation passes and the policy is reached.
        $response = $this->actingAs($admin)
            ->putJson("/api/v1/franchises/{$franchise->id}", [
                'name'     => 'Hacked',
                'country'  => 'Mexico',
                'timezone' => 'America/Mexico_City',
                'phone'    => '+52 55 1234 5678',
                'address'  => 'Calle Falsa 123',
            ]);

        $response->assertStatus(403);
    }

    // ===========================================================================
    // PATCH /api/v1/franchises/{franchise}/toggle-status
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_toggle_status(): void
    {
        $franchise = Franchise::factory()->create();

        $response = $this->patchJson("/api/v1/franchises/{$franchise->id}/toggle-status");

        $response->assertStatus(401);
    }

    public function test_superadmin_can_deactivate_active_franchise(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create(['is_active' => true]);

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/toggle-status");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('franchises', [
            'id' => $franchise->id,
            'is_active' => false,
        ]);
    }

    public function test_superadmin_can_activate_inactive_franchise(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->inactive()->create();

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/toggle-status");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('franchises', [
            'id' => $franchise->id,
            'is_active' => true,
        ]);
    }

    public function test_toggle_status_returns_correct_message_on_deactivation(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create(['is_active' => true]);

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/toggle-status");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'franchises.deactivated_success');
    }

    public function test_toggle_status_returns_correct_message_on_activation(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->inactive()->create();

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/toggle-status");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'franchises.activated_success');
    }

    public function test_admin_sm_cannot_toggle_franchise_status(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/toggle-status");

        $response->assertStatus(403);
    }

    public function test_toggle_status_dispatches_franchise_status_changed_event(): void
    {
        Event::fake([FranchiseStatusChanged::class]);

        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create(['is_active' => true]);

        $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/toggle-status");

        Event::assertDispatched(FranchiseStatusChanged::class, function ($event) use ($franchise) {
            return $event->franchise->id === $franchise->id;
        });
    }

    // ===========================================================================
    // DELETE /api/v1/franchises/{franchise}  (destroy)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_franchise_delete(): void
    {
        $franchise = Franchise::factory()->create();

        $response = $this->deleteJson("/api/v1/franchises/{$franchise->id}");

        $response->assertStatus(401);
    }

    public function test_superadmin_can_delete_franchise(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $franchiseId = $franchise->id;

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/franchises/{$franchiseId}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'franchises.deleted_success');

        // Soft-deleted — still in DB but with deleted_at set
        $this->assertSoftDeleted('franchises', ['id' => $franchiseId]);
    }

    public function test_admin_sm_cannot_delete_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/franchises/{$franchise->id}");

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_delete_franchise(): void
    {
        $user = $this->createRegularUser();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/franchises/{$franchise->id}");

        $response->assertStatus(403);
    }

    // ===========================================================================
    // Active/Inactive status — card badge and visual state
    // ===========================================================================

    public function test_active_franchise_returns_is_active_true(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create(['is_active' => true]);

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', true);
    }

    public function test_inactive_franchise_returns_is_active_false(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->inactive()->create();

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', false);
    }

    public function test_franchise_index_includes_both_active_and_inactive(): void
    {
        $superadmin = $this->createSuperadmin();
        Franchise::factory()->count(2)->create(['is_active' => true]);
        Franchise::factory()->count(1)->inactive()->create();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/franchises');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');

        $statuses = collect($response->json('data'))->pluck('is_active');
        $this->assertContains(true, $statuses->all());
        $this->assertContains(false, $statuses->all());
    }

    // ===========================================================================
    // Card data completeness — all fields needed for the UI card
    // ===========================================================================

    public function test_franchise_resource_returns_all_card_fields(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create([
            'name' => 'Card Test Franchise',
            'type' => 'sm',
            'email' => 'card@test.com',
            'country' => 'Argentina',
            'is_active' => true,
            'phone' => '+54-11-4444-5555',
            'region' => 'Buenos Aires',
            'address' => '123 Main St',
            'timezone' => 'America/Argentina/Buenos_Aires',
        ]);

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Card Test Franchise');
        $response->assertJsonPath('data.type', 'sm');
        $response->assertJsonPath('data.email', 'card@test.com');
        $response->assertJsonPath('data.country', 'Argentina');
        $response->assertJsonPath('data.is_active', true);
        $response->assertJsonPath('data.phone', '+54-11-4444-5555');
    }

    // ===========================================================================
    // Pagination (superadmin sees all paginated)
    // ===========================================================================

    public function test_franchise_index_is_paginated(): void
    {
        $superadmin = $this->createSuperadmin();
        Franchise::factory()->count(30)->create();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/franchises');

        $response->assertStatus(200);
        // Paginated at 25 per page
        $response->assertJsonCount(25, 'data');
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('meta.total', 30);
    }

    public function test_franchise_index_second_page(): void
    {
        $superadmin = $this->createSuperadmin();
        Franchise::factory()->count(30)->create();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/franchises?page=2');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.current_page', 2);
    }

    // ===========================================================================
    // WILT-71 replanteado — name uniqueness, no email required
    // ===========================================================================

    public function test_can_create_franchise_without_email(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/franchises', [
            'name'     => 'Test Franchise',
            'country'  => 'United States',
            'timezone' => 'America/New_York',
            'phone'    => '+1 555-0123',
            'address'  => '123 Main St, New York, NY 10001',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('franchises', [
            'name'    => 'Test Franchise',
            'country' => 'United States',
        ]);
    }

    public function test_cannot_create_duplicate_franchise_name(): void
    {
        $superadmin = $this->createSuperadmin();

        Franchise::factory()->create([
            'name' => 'Duplicate Name',
        ]);

        $response = $this->actingAs($superadmin)->postJson('/api/v1/franchises', [
            'name'     => 'Duplicate Name',
            'country'  => 'Canada',
            'timezone' => 'America/Toronto',
            'phone'    => '+1 416-987-6543',
            'address'  => '456 Oak Ave, Toronto',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_all_franchise_fields_required(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/franchises', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'country', 'timezone', 'phone', 'address']);
    }
}

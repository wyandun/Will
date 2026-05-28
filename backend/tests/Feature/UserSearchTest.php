<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Tests for GET /api/v1/users/search — the lightweight user lookup used
 * by the calendar "Add Guests" picker.
 *
 * Scoping rules under test:
 *   - superadmin / system_admin / system_admin_readonly: see all users
 *   - everyone else: scoped to their own sm_franchise_id
 *   - results capped at 10, only accepted users, self excluded
 */
class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatieRole::firstOrCreate(['name' => Role::SUPERADMIN, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::SB_OWNER, 'guard_name' => 'web']);
    }

    private function createSuperadmin(): User
    {
        $user = User::factory()->create(['invitation_accepted_at' => now()]);
        $user->assignRole(Role::SUPERADMIN);

        return $user;
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson('/api/v1/users/search?q=anything');

        $response->assertStatus(401);
    }

    public function test_search_returns_matching_users_by_name(): void
    {
        $admin = $this->createSuperadmin();

        User::factory()->create(['name' => 'Alice Smith', 'invitation_accepted_at' => now()]);
        User::factory()->create(['name' => 'Bob Jones', 'invitation_accepted_at' => now()]);
        User::factory()->create(['name' => 'Charlie Smith', 'invitation_accepted_at' => now()]);

        $response = $this->actingAs($admin)->getJson('/api/v1/users/search?q=Smith');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Alice Smith', $names);
        $this->assertContains('Charlie Smith', $names);
        $this->assertNotContains('Bob Jones', $names);
    }

    public function test_search_returns_matching_users_by_email(): void
    {
        $admin = $this->createSuperadmin();

        User::factory()->create([
            'name' => 'Unique Person',
            'email' => 'findme@example.com',
            'invitation_accepted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/users/search?q=findme');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.email', 'findme@example.com');
    }

    public function test_search_caps_results_at_10(): void
    {
        $admin = $this->createSuperadmin();

        for ($i = 0; $i < 15; $i++) {
            User::factory()->create([
                'name' => "Match User {$i}",
                'invitation_accepted_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/v1/users/search?q=Match');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
    }

    public function test_search_excludes_pending_invitations(): void
    {
        $admin = $this->createSuperadmin();

        User::factory()->create([
            'name' => 'Pending User',
            'invitation_accepted_at' => null,
        ]);
        User::factory()->create([
            'name' => 'Accepted User',
            'invitation_accepted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/users/search?q=User');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Accepted User', $names);
        $this->assertNotContains('Pending User', $names);
    }

    public function test_non_admin_user_is_scoped_to_own_franchise(): void
    {
        $franchiseA = Franchise::create([
            'name' => 'SM Florida',
            'type' => 'sm',
            'is_active' => true,
        ]);
        $franchiseB = Franchise::create([
            'name' => 'SM Texas',
            'type' => 'sm',
            'is_active' => true,
        ]);

        $caller = User::factory()->create([
            'sm_franchise_id' => $franchiseA->id,
            'invitation_accepted_at' => now(),
        ]);
        $caller->assignRole(Role::SB_OWNER);

        $sameFranchise = User::factory()->create([
            'name' => 'Local Mate',
            'sm_franchise_id' => $franchiseA->id,
            'invitation_accepted_at' => now(),
        ]);
        $otherFranchise = User::factory()->create([
            'name' => 'Remote Mate',
            'sm_franchise_id' => $franchiseB->id,
            'invitation_accepted_at' => now(),
        ]);

        $response = $this->actingAs($caller)->getJson('/api/v1/users/search?q=Mate');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Local Mate', $names);
        $this->assertNotContains('Remote Mate', $names);
    }

    public function test_superadmin_sees_users_across_all_franchises(): void
    {
        $admin = $this->createSuperadmin();

        $franchiseA = Franchise::create(['name' => 'SM FL', 'type' => 'sm', 'is_active' => true]);
        $franchiseB = Franchise::create(['name' => 'SM TX', 'type' => 'sm', 'is_active' => true]);

        User::factory()->create([
            'name' => 'Mate FL',
            'sm_franchise_id' => $franchiseA->id,
            'invitation_accepted_at' => now(),
        ]);
        User::factory()->create([
            'name' => 'Mate TX',
            'sm_franchise_id' => $franchiseB->id,
            'invitation_accepted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/users/search?q=Mate');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Mate FL', $names);
        $this->assertContains('Mate TX', $names);
    }

    public function test_search_excludes_the_calling_user(): void
    {
        $admin = $this->createSuperadmin();
        $admin->update(['name' => 'Calling Person']);

        User::factory()->create([
            'name' => 'Other Person',
            'invitation_accepted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/users/search?q=Person');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($admin->id, $ids);
    }

    public function test_response_includes_expected_fields(): void
    {
        $admin = $this->createSuperadmin();
        User::factory()->create([
            'name' => 'Field Check',
            'email' => 'fieldcheck@example.com',
            'invitation_accepted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/users/search?q=Field');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'avatar_url'],
            ],
        ]);
    }
}

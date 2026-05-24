<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class FranchiseClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatieRole::firstOrCreate(['name' => Role::SUPERADMIN, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::SB_OWNER, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::BB_EMPLOYEE, 'guard_name' => 'web']);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function createSuperadmin(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->assignRole(Role::SUPERADMIN);

        return $user;
    }

    private function createAdminSm(Franchise $franchise, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge(['sm_franchise_id' => $franchise->id], $attrs));
        $user->assignRole(Role::ADMIN_SM);

        return $user;
    }

    private function createSbOwner(Franchise $franchise, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge(['sm_franchise_id' => $franchise->id], $attrs));
        $user->assignRole(Role::SB_OWNER);
        UserPermission::syncForRole($user->id, Role::SB_OWNER);

        return $user;
    }

    private function validProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Updated Client',
            'email' => 'updated@example.com',
        ], $overrides);
    }

    // ===========================================================================
    // PATCH /api/v1/franchises/{franchise}/clients/{user}  (update)
    // ===========================================================================

    public function test_superadmin_can_update_franchise_client(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createSbOwner($franchise);

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}", $this->validProfilePayload());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('users', ['id' => $client->id, 'name' => 'Updated Client']);
    }

    public function test_admin_sm_can_update_own_franchise_client(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);
        $client = $this->createSbOwner($franchise, ['email' => 'client@example.com']);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}", $this->validProfilePayload(['email' => 'client@example.com']));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_admin_sm_cannot_update_client_in_other_franchise(): void
    {
        $franchise1 = Franchise::factory()->create();
        $franchise2 = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise1);
        $client = $this->createSbOwner($franchise2);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$franchise2->id}/clients/{$client->id}", $this->validProfilePayload());

        $response->assertStatus(403);
    }

    public function test_update_client_returns_404_for_wrong_franchise(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise1 = Franchise::factory()->create();
        $franchise2 = Franchise::factory()->create();
        $client = $this->createSbOwner($franchise2);

        // Client belongs to franchise2, but we send franchise1 in URL
        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise1->id}/clients/{$client->id}", $this->validProfilePayload());

        $response->assertStatus(404);
    }

    public function test_update_rejects_non_client_role(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);

        // admin_sm is not a client role — should return 403
        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$admin->id}", $this->validProfilePayload());

        $response->assertStatus(403);
    }

    public function test_sb_owner_cannot_manage_clients(): void
    {
        $franchise = Franchise::factory()->create();
        $actor = $this->createSbOwner($franchise, ['email' => 'actor@example.com']);
        $client = $this->createSbOwner($franchise, ['email' => 'client@example.com']);

        $response = $this->actingAs($actor)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}", $this->validProfilePayload());

        $response->assertStatus(403);
    }

    // ===========================================================================
    // PATCH /api/v1/franchises/{franchise}/clients/{user}/password
    // ===========================================================================

    public function test_superadmin_can_reset_client_password(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createSbOwner($franchise);

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}/password", [
                'password' => 'newsecurepassword123',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertTrue(Hash::check('newsecurepassword123', $client->fresh()->password));
    }

    // ===========================================================================
    // DELETE /api/v1/franchises/{franchise}/clients/{user}  (deactivate)
    // ===========================================================================

    public function test_superadmin_can_deactivate_client(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createSbOwner($franchise);

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertSoftDeleted('users', ['id' => $client->id]);
    }

    public function test_deactivated_client_appears_in_members_list(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createSbOwner($franchise);
        $client->delete(); // soft delete

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(200);
        $deactivatedIds = collect($response->json('data.deactivated_clients'))->pluck('id');
        $this->assertTrue($deactivatedIds->contains($client->id));
    }

    // ===========================================================================
    // PATCH /api/v1/franchises/{franchise}/clients/{user}/restore
    // ===========================================================================

    public function test_superadmin_can_restore_deactivated_client(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createSbOwner($franchise);
        $client->delete(); // soft delete

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}/restore");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('users', ['id' => $client->id, 'deleted_at' => null]);
    }

    public function test_admin_sm_can_restore_own_franchise_client(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);
        $client = $this->createSbOwner($franchise);
        $client->delete();

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}/restore");

        $response->assertStatus(200);
    }

    // ===========================================================================
    // GET /api/v1/franchises/{franchise}/clients/{user}/permissions
    // ===========================================================================

    public function test_superadmin_can_get_client_permissions(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createSbOwner($franchise);

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}/permissions");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data']);
    }

    // ===========================================================================
    // PUT /api/v1/franchises/{franchise}/clients/{user}/permissions
    // ===========================================================================

    public function test_superadmin_can_update_client_permissions(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createSbOwner($franchise);

        $payload = [
            'permissions' => [
                ['module' => 'feed', 'can_read' => true, 'can_write' => false],
                ['module' => 'calendar', 'can_read' => true, 'can_write' => true],
            ],
        ];

        $response = $this->actingAs($superadmin)
            ->putJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}/permissions", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class FranchiseClientTest extends TestCase
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

    private function createAdminSm(Franchise $franchise): User
    {
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);

        $user = User::factory()->create(['sm_franchise_id' => $franchise->id]);
        $user->assignRole(Role::ADMIN_SM);

        return $user;
    }

    private function createClient(Franchise $franchise, string $role = Role::SB_OWNER, array $attributes = []): User
    {
        SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create(array_merge([
            'sm_franchise_id' => $franchise->id,
            'invitation_accepted_at' => now(),
        ], $attributes));
        $user->assignRole($role);

        return $user;
    }

    // ===========================================================================
    // UPDATE  PATCH /api/v1/franchises/{franchise}/clients/{user}
    // ===========================================================================

    public function test_superadmin_can_update_franchise_client(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createClient($franchise);

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}", [
                'name' => 'Updated Name',
                'email' => $client->email,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('users', ['id' => $client->id, 'name' => 'Updated Name']);
    }

    public function test_admin_sm_can_update_own_franchise_client(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);
        $client = $this->createClient($franchise);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}", [
                'name' => 'Admin Updated',
                'email' => $client->email,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_admin_sm_cannot_update_other_franchise_client(): void
    {
        $myFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($myFranchise);
        $client = $this->createClient($otherFranchise);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$otherFranchise->id}/clients/{$client->id}", [
                'name' => 'Should Fail',
                'email' => $client->email,
            ]);

        $response->assertStatus(403);
    }

    // ===========================================================================
    // RESET PASSWORD  PATCH /api/v1/franchises/{franchise}/clients/{user}/password
    // ===========================================================================

    public function test_superadmin_can_reset_client_password(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createClient($franchise);

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}/password", [
                'password' => 'NewSecurePass123!',
                'password_confirmation' => 'NewSecurePass123!',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    // ===========================================================================
    // DEACTIVATE  DELETE /api/v1/franchises/{franchise}/clients/{user}
    // ===========================================================================

    public function test_superadmin_can_deactivate_client(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createClient($franchise);

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertSoftDeleted('users', ['id' => $client->id]);
    }

    public function test_deactivating_sb_owner_cascades_to_investors(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $company = Company::create([
            'name' => 'Test LLC',
            'sm_franchise_id' => $franchise->id,
        ]);

        $sbOwner = $this->createClient($franchise, Role::SB_OWNER, ['company_id' => $company->id]);
        $investor1 = $this->createClient($franchise, Role::BB_EMPLOYEE, ['company_id' => $company->id]);
        $investor2 = $this->createClient($franchise, Role::BB_EMPLOYEE, ['company_id' => $company->id]);

        // Investor in a different company should NOT be affected
        $otherCompany = Company::create([
            'name' => 'Other LLC',
            'sm_franchise_id' => $franchise->id,
        ]);
        $unrelatedInvestor = $this->createClient($franchise, Role::BB_EMPLOYEE, ['company_id' => $otherCompany->id]);

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/franchises/{$franchise->id}/clients/{$sbOwner->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('users', ['id' => $sbOwner->id]);
        $this->assertSoftDeleted('users', ['id' => $investor1->id]);
        $this->assertSoftDeleted('users', ['id' => $investor2->id]);
        $this->assertNotSoftDeleted('users', ['id' => $unrelatedInvestor->id]);
    }

    public function test_cascade_deactivation_revokes_investor_tokens_in_bulk(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = Company::create([
            'name' => 'Bulk Cascade LLC',
            'sm_franchise_id' => $franchise->id,
        ]);

        $sbOwner = $this->createClient($franchise, Role::SB_OWNER, ['company_id' => $company->id]);

        // Seed many investors, each with an active Sanctum token, so the bulk
        // path is meaningfully exercised vs a per-model loop.
        $investors = collect();
        for ($i = 0; $i < 15; $i++) {
            $investor = $this->createClient($franchise, Role::BB_EMPLOYEE, ['company_id' => $company->id]);
            $investor->createToken('test');
            $investors->push($investor);
        }

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/franchises/{$franchise->id}/clients/{$sbOwner->id}");

        $response->assertStatus(200);

        foreach ($investors as $investor) {
            $this->assertSoftDeleted('users', ['id' => $investor->id]);
            $this->assertDatabaseMissing('personal_access_tokens', [
                'tokenable_type' => User::class,
                'tokenable_id' => $investor->id,
            ]);
        }
    }

    // ===========================================================================
    // RESTORE  PATCH /api/v1/franchises/{franchise}/clients/{user}/restore
    // ===========================================================================

    public function test_superadmin_can_restore_deactivated_client(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createClient($franchise);
        $client->delete();

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}/restore");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotSoftDeleted('users', ['id' => $client->id]);
    }

    // ===========================================================================
    // PERMISSIONS  GET + PUT /api/v1/franchises/{franchise}/clients/{user}/permissions
    // ===========================================================================

    public function test_superadmin_can_get_client_permissions(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createClient($franchise);

        UserPermission::updateForUser($client->id, [
            ['module' => 'feed', 'can_read' => true, 'can_write' => false],
        ]);

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}/permissions");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_superadmin_can_update_client_permissions(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createClient($franchise);

        $response = $this->actingAs($superadmin)
            ->putJson("/api/v1/franchises/{$franchise->id}/clients/{$client->id}/permissions", [
                'permissions' => [
                    ['module' => 'feed', 'can_read' => true, 'can_write' => true],
                    ['module' => 'contracts', 'can_read' => true, 'can_write' => false],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $client->id,
            'module' => 'feed',
            'can_read' => true,
            'can_write' => true,
        ]);
    }

    // ===========================================================================
    // EDGE CASES
    // ===========================================================================

    public function test_update_client_returns_404_for_wrong_franchise(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $client = $this->createClient($franchise);

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$otherFranchise->id}/clients/{$client->id}", [
                'name' => 'Wrong Franchise',
                'email' => $client->email,
            ]);

        $response->assertStatus(404);
    }

    public function test_update_rejects_non_client_role(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/v1/franchises/{$franchise->id}/clients/{$admin->id}", [
                'name' => 'Not A Client',
                'email' => $admin->email,
            ]);

        $response->assertStatus(403);
    }

    public function test_deactivated_clients_appear_in_members_list(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $client = $this->createClient($franchise);
        $client->delete();

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(200);
        $response->assertJsonPath('data.deactivated_clients_count', 1);

        $deactivated = $response->json('data.deactivated_clients');
        $this->assertNotEmpty($deactivated);
        $this->assertEquals($client->id, $deactivated[0]['id']);
    }
}

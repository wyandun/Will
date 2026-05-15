<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class FranchiseMemberTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function createSuperadmin(array $attributes = []): User
    {
        SpatieRole::firstOrCreate(['name' => Role::SUPERADMIN, 'guard_name' => 'web']);

        $user = User::factory()->create($attributes);
        $user->assignRole(Role::SUPERADMIN);

        return $user;
    }

    private function createAdminSm(Franchise $franchise, array $attributes = []): User
    {
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);

        $user = User::factory()->create(array_merge([
            'sm_franchise_id' => $franchise->id,
        ], $attributes));
        $user->assignRole(Role::ADMIN_SM);

        return $user;
    }

    // ===========================================================================
    // GET /api/v1/franchises/{franchise}/members
    // ===========================================================================

    public function test_superadmin_can_list_members(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise  = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'franchise_id',
                'franchise_name',
                'admins_count',
                'clients_count',
                'admins',
                'clients',
            ],
        ]);
    }

    public function test_admin_sm_can_list_own_franchise_members(): void
    {
        $franchise = Franchise::factory()->create();
        $admin     = $this->createAdminSm($franchise);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.franchise_id', $franchise->id);
    }

    public function test_admin_sm_cannot_list_other_franchise_members(): void
    {
        $myFranchise    = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin          = $this->createAdminSm($myFranchise);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/franchises/{$otherFranchise->id}/members");

        $response->assertStatus(403);
    }

    public function test_members_response_contains_correct_counts(): void
    {
        SpatieRole::firstOrCreate(['name' => Role::SB_OWNER, 'guard_name' => 'web']);

        $superadmin = $this->createSuperadmin();
        $franchise  = Franchise::factory()->create();

        // 2 admins
        $this->createAdminSm($franchise);
        $this->createAdminSm($franchise);

        // 1 client
        $client = User::factory()->create([
            'sm_franchise_id'        => $franchise->id,
            'invitation_accepted_at' => now(),
        ]);
        $client->assignRole(Role::SB_OWNER);

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.admins_count'));
        $this->assertEquals(1, $response->json('data.clients_count'));
    }
}

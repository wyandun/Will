<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Franchise;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class FranchiseMemberTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Setup — ensure all Spatie roles exist before every test
    // ---------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        // FranchiseMemberService::getMembers() uses ->role() scope which does a
        // DB lookup. If the role record doesn't exist it throws RoleDoesNotExist
        // → 500. Pre-create every role the service may query.
        $roles = [
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::ADMIN_SM,
            Role::SB_OWNER,
            Role::BB_EMPLOYEE,
        ];

        foreach ($roles as $roleName) {
            SpatieRole::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

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

    private function ensureRole(string $roleName): void
    {
        SpatieRole::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    }

    // ===========================================================================
    // GET /api/v1/franchises/{franchise}/members
    // ===========================================================================

    public function test_unauthenticated_user_cannot_get_members(): void
    {
        $franchise = Franchise::factory()->create();

        $response = $this->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(401);
    }

    public function test_superadmin_can_get_franchise_members(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'admins',
                'clients',
            ],
        ]);
    }

    public function test_members_returns_admin_sm_users_in_admins_list(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise, ['name' => 'Jane Admin']);

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(200);
        $admins = $response->json('data.admins');
        $this->assertCount(1, $admins);
        $this->assertEquals('Jane Admin', $admins[0]['name']);
    }

    public function test_members_returns_sb_owner_in_clients_list(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::SB_OWNER);

        $client = User::factory()->create([
            'sm_franchise_id' => $franchise->id,
            'name' => 'Bob Owner',
        ]);
        $client->assignRole(Role::SB_OWNER);

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(200);
        $clients = $response->json('data.clients');
        $this->assertCount(1, $clients);
        $this->assertEquals('Bob Owner', $clients[0]['name']);
        $this->assertEquals(Role::SB_OWNER, $clients[0]['role']);
    }

    public function test_members_returns_bb_employee_in_clients_list(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::BB_EMPLOYEE);

        $investor = User::factory()->create([
            'sm_franchise_id' => $franchise->id,
            'name' => 'Alice Investor',
        ]);
        $investor->assignRole(Role::BB_EMPLOYEE);

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(200);
        $clients = $response->json('data.clients');
        $this->assertCount(1, $clients);
        $this->assertEquals('Alice Investor', $clients[0]['name']);
        $this->assertEquals(Role::BB_EMPLOYEE, $clients[0]['role']);
    }

    public function test_admin_sm_can_get_their_own_franchise_members(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['admins', 'clients'],
        ]);
    }

    public function test_admin_sm_cannot_get_another_franchises_members(): void
    {
        $myFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($myFranchise);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/franchises/{$otherFranchise->id}/members");

        $response->assertStatus(403);
    }

    public function test_members_only_shows_users_belonging_to_franchise(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();

        $this->createAdminSm($franchise, ['name' => 'My Admin']);
        $this->createAdminSm($otherFranchise, ['name' => 'Other Admin']);

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/franchises/{$franchise->id}/members");

        $response->assertStatus(200);
        $admins = $response->json('data.admins');
        $this->assertCount(1, $admins);
        $this->assertEquals('My Admin', $admins[0]['name']);
    }

    // ===========================================================================
    // POST /api/v1/franchises/{franchise}/admins
    // ===========================================================================

    public function test_unauthenticated_user_cannot_create_admin(): void
    {
        $franchise = Franchise::factory()->create();

        $response = $this->postJson("/api/v1/franchises/{$franchise->id}/admins", [
            'name' => 'New Admin',
            'email' => 'newadmin@test.com',
            'password' => 'password123',
            'area' => 'full_access',
        ]);

        $response->assertStatus(401);
    }

    public function test_superadmin_can_create_franchise_admin(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::ADMIN_SM);

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'New Admin',
                'email' => 'newadmin@test.com',
                'password' => 'password123',
                'area' => 'full_access',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.email', 'newadmin@test.com');

        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@test.com',
            'sm_franchise_id' => $franchise->id,
        ]);
    }

    public function test_created_admin_has_admin_sm_role(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::ADMIN_SM);

        $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'Role Admin',
                'email' => 'roleadmin@test.com',
                'password' => 'password123',
                'area' => 'full_access',
            ]);

        $user = User::where('email', 'roleadmin@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(Role::ADMIN_SM));
    }

    public function test_created_admin_has_correct_area_stored(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::ADMIN_SM);

        $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'Area Admin',
                'email' => 'areaadmin@test.com',
                'password' => 'password123',
                'area' => 'accounting',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'areaadmin@test.com',
            'area' => 'accounting',
        ]);
    }

    public function test_full_access_area_assigns_all_module_permissions(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::ADMIN_SM);

        $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'Full Admin',
                'email' => 'fulladmin@test.com',
                'password' => 'password123',
                'area' => 'full_access',
            ]);

        $user = User::where('email', 'fulladmin@test.com')->first();
        $allModules = ['feed', 'contracts', 'repository', 'processes', 'accounting',
            'inventory', 'tracking', 'catalog', 'calendar', 'applications'];

        foreach ($allModules as $module) {
            $this->assertDatabaseHas('user_permissions', [
                'user_id' => $user->id,
                'module' => $module,
                'can_read' => true,
                'can_write' => true,
            ]);
        }
    }

    public function test_accounting_area_assigns_only_accounting_permissions(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::ADMIN_SM);

        $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'Accounting Admin',
                'email' => 'accountingadmin@test.com',
                'password' => 'password123',
                'area' => 'accounting',
            ]);

        $user = User::where('email', 'accountingadmin@test.com')->first();

        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'module' => 'accounting',
            'can_read' => true,
            'can_write' => true,
        ]);

        $permissionCount = UserPermission::where('user_id', $user->id)->count();
        $this->assertEquals(1, $permissionCount);
    }

    public function test_marketing_area_assigns_feed_and_calendar_permissions(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::ADMIN_SM);

        $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'Marketing Admin',
                'email' => 'marketingadmin@test.com',
                'password' => 'password123',
                'area' => 'marketing',
            ]);

        $user = User::where('email', 'marketingadmin@test.com')->first();

        $this->assertDatabaseHas('user_permissions', ['user_id' => $user->id, 'module' => 'feed']);
        $this->assertDatabaseHas('user_permissions', ['user_id' => $user->id, 'module' => 'calendar']);
        $permissionCount = UserPermission::where('user_id', $user->id)->count();
        $this->assertEquals(2, $permissionCount);
    }

    public function test_store_admin_validates_email_uniqueness(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        User::factory()->create(['email' => 'existing@test.com']);

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'Duplicate',
                'email' => 'existing@test.com',
                'password' => 'password123',
                'area' => 'full_access',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_store_admin_validates_required_name(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'email' => 'admin@test.com',
                'password' => 'password123',
                'area' => 'full_access',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_admin_validates_required_area(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'No Area',
                'email' => 'noarea@test.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['area']);
    }

    public function test_store_admin_validates_area_must_be_valid_value(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'Bad Area',
                'email' => 'badarea@test.com',
                'password' => 'password123',
                'area' => 'invalid_area',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['area']);
    }

    public function test_store_admin_validates_password_minimum_length(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'Short Pass',
                'email' => 'shortpass@test.com',
                'password' => 'short',
                'area' => 'full_access',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_store_admin_validates_email_format(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'Bad Email',
                'email' => 'not-an-email',
                'password' => 'password123',
                'area' => 'full_access',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_admin_sm_can_add_admin_to_their_own_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);
        $this->ensureRole(Role::ADMIN_SM);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/franchises/{$franchise->id}/admins", [
                'name' => 'New Member',
                'email' => 'newmember@test.com',
                'password' => 'password123',
                'area' => 'operations',
            ]);

        $response->assertStatus(201);
    }

    public function test_admin_sm_cannot_add_admin_to_another_franchise(): void
    {
        $myFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($myFranchise);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/franchises/{$otherFranchise->id}/admins", [
                'name' => 'Intruder',
                'email' => 'intruder@test.com',
                'password' => 'password123',
                'area' => 'full_access',
            ]);

        $response->assertStatus(403);
    }

    // ===========================================================================
    // POST /api/v1/franchises/{franchise}/clients
    // ===========================================================================

    public function test_unauthenticated_user_cannot_create_client(): void
    {
        $franchise = Franchise::factory()->create();

        $response = $this->postJson("/api/v1/franchises/{$franchise->id}/clients", [
            'name' => 'New Client',
            'email' => 'newclient@test.com',
            'password' => 'password123',
            'client_type' => 'owner',
        ]);

        $response->assertStatus(401);
    }

    public function test_superadmin_can_create_owner_client(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::SB_OWNER);

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/clients", [
                'name' => 'SB Owner',
                'email' => 'sbowner@test.com',
                'password' => 'password123',
                'client_type' => 'owner',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.email', 'sbowner@test.com');

        $this->assertDatabaseHas('users', [
            'email' => 'sbowner@test.com',
            'sm_franchise_id' => $franchise->id,
        ]);
    }

    public function test_owner_client_type_assigns_sb_owner_role(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::SB_OWNER);

        $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/clients", [
                'name' => 'SB Owner Role',
                'email' => 'sbownerrole@test.com',
                'password' => 'password123',
                'client_type' => 'owner',
            ]);

        $user = User::where('email', 'sbownerrole@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(Role::SB_OWNER));
        $this->assertFalse($user->hasRole(Role::BB_EMPLOYEE));
    }

    public function test_investor_client_type_assigns_bb_employee_role(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::BB_EMPLOYEE);

        $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/clients", [
                'name' => 'Investor',
                'email' => 'investor@test.com',
                'password' => 'password123',
                'client_type' => 'investor',
            ]);

        $user = User::where('email', 'investor@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(Role::BB_EMPLOYEE));
        $this->assertFalse($user->hasRole(Role::SB_OWNER));
    }

    public function test_store_client_validates_email_uniqueness(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        User::factory()->create(['email' => 'taken@test.com']);

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/clients", [
                'name' => 'Duplicate',
                'email' => 'taken@test.com',
                'password' => 'password123',
                'client_type' => 'owner',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_store_client_validates_required_name(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/clients", [
                'email' => 'client@test.com',
                'password' => 'password123',
                'client_type' => 'owner',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_client_validates_required_client_type(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/clients", [
                'name' => 'No Type',
                'email' => 'notype@test.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_type']);
    }

    public function test_store_client_validates_client_type_must_be_owner_or_investor(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/clients", [
                'name' => 'Wrong Type',
                'email' => 'wrongtype@test.com',
                'password' => 'password123',
                'client_type' => 'admin',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_type']);
    }

    public function test_store_client_validates_password_minimum_length(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/clients", [
                'name' => 'Short Pass',
                'email' => 'shortpass@test.com',
                'password' => 'short',
                'client_type' => 'owner',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_admin_sm_can_add_client_to_their_own_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);
        $this->ensureRole(Role::SB_OWNER);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/franchises/{$franchise->id}/clients", [
                'name' => 'My Client',
                'email' => 'myclient@test.com',
                'password' => 'password123',
                'client_type' => 'owner',
            ]);

        $response->assertStatus(201);
    }

    public function test_admin_sm_cannot_add_client_to_another_franchise(): void
    {
        $myFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($myFranchise);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/franchises/{$otherFranchise->id}/clients", [
                'name' => 'Intruder',
                'email' => 'intruderclient@test.com',
                'password' => 'password123',
                'client_type' => 'owner',
            ]);

        $response->assertStatus(403);
    }

    public function test_created_client_is_immediately_activated(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $this->ensureRole(Role::SB_OWNER);

        $this->actingAs($superadmin)
            ->postJson("/api/v1/franchises/{$franchise->id}/clients", [
                'name' => 'Active Client',
                'email' => 'activeclient@test.com',
                'password' => 'password123',
                'client_type' => 'owner',
            ]);

        $user = User::where('email', 'activeclient@test.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->invitation_accepted_at);
    }
}

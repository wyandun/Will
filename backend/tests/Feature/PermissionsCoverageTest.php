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

/**
 * Coverage tests for:
 * 1. syncForRole() — all role branches
 * 2. POST /feed/posts — SYSTEM_ADMIN and SYSTEM_ADMIN_READONLY access
 * 3. GET /invitations — each role that has access
 */
class PermissionsCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::SYSTEM_ADMIN_READONLY,
            Role::ADMIN_SM,
            Role::SB_OWNER,
            Role::SB_EMPLOYEE,
            Role::BB_EMPLOYEE,
            Role::SUB_FRANCHISE_OWNER,
            Role::SUB_FRANCHISE_ADMIN,
        ] as $role) {
            SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function createUserWithRole(string $role, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->assignRole($role);

        return $user;
    }

    // ===========================================================================
    // 1. Unit tests for syncForRole() — all role branches
    // ===========================================================================

    public function test_sync_for_role_superadmin_gets_read_write_on_all_modules(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, Role::SUPERADMIN);

        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertCount(9, $perms);
        $this->assertEqualsCanonicalizing(UserPermission::ALL_MODULES, $perms->pluck('module')->all());
        $this->assertTrue($perms->every(fn ($p) => $p->can_read === true && $p->can_write === true));
    }

    public function test_sync_for_role_system_admin_gets_read_write_on_all_modules(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, Role::SYSTEM_ADMIN);

        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertCount(9, $perms);
        $this->assertEqualsCanonicalizing(UserPermission::ALL_MODULES, $perms->pluck('module')->all());
        $this->assertTrue($perms->every(fn ($p) => $p->can_read === true && $p->can_write === true));
    }

    public function test_sync_for_role_admin_sm_gets_read_write_on_all_modules(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, Role::ADMIN_SM);

        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertCount(9, $perms);
        $this->assertEqualsCanonicalizing(UserPermission::ALL_MODULES, $perms->pluck('module')->all());
        $this->assertTrue($perms->every(fn ($p) => $p->can_read === true && $p->can_write === true));
    }

    public function test_sync_for_role_system_admin_readonly_gets_read_only_on_all_modules(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, Role::SYSTEM_ADMIN_READONLY);

        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertCount(9, $perms);
        $this->assertEqualsCanonicalizing(UserPermission::ALL_MODULES, $perms->pluck('module')->all());
        $this->assertTrue($perms->every(fn ($p) => $p->can_read === true && $p->can_write === false));
    }

    public function test_sync_for_role_sb_owner_gets_read_all_write_some(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, Role::SB_OWNER);

        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertCount(9, $perms);
        $this->assertTrue($perms->every(fn ($p) => $p->can_read === true));

        // sb_owner gets write on feed, calendar, contracts only.
        $writeModules = ['feed', 'calendar', 'contracts'];
        foreach ($perms as $p) {
            if (in_array($p->module, $writeModules, true)) {
                $this->assertTrue($p->can_write, "sb_owner should have write on {$p->module}");
            } else {
                $this->assertFalse($p->can_write, "sb_owner should NOT have write on {$p->module}");
            }
        }
    }

    public function test_sync_for_role_sb_employee_gets_read_only_on_all_modules(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, Role::SB_EMPLOYEE);

        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertCount(9, $perms);
        $this->assertTrue($perms->every(fn ($p) => $p->can_read === true && $p->can_write === false));
    }

    public function test_sync_for_role_bb_employee_gets_read_only_on_all_modules(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, Role::BB_EMPLOYEE);

        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertCount(9, $perms);
        $this->assertTrue($perms->every(fn ($p) => $p->can_read === true && $p->can_write === false));
    }

    public function test_sync_for_role_sub_franchise_owner_gets_read_only_on_all_modules(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, Role::SUB_FRANCHISE_OWNER);

        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertCount(9, $perms);
        $this->assertTrue($perms->every(fn ($p) => $p->can_read === true && $p->can_write === false));
    }

    public function test_sync_for_role_sub_franchise_admin_gets_read_only_on_all_modules(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, Role::SUB_FRANCHISE_ADMIN);

        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertCount(9, $perms);
        $this->assertTrue($perms->every(fn ($p) => $p->can_read === true && $p->can_write === false));
    }

    public function test_sync_for_role_unknown_role_defaults_to_read_only(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, 'unknown_role');

        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertCount(9, $perms);
        $this->assertTrue($perms->every(fn ($p) => $p->can_read === true && $p->can_write === false));
    }

    public function test_sync_for_role_is_idempotent(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, Role::SYSTEM_ADMIN);
        UserPermission::syncForRole($user->id, Role::SYSTEM_ADMIN);

        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertCount(9, $perms);
    }

    public function test_sync_for_role_upgrade_from_readonly_to_admin_updates_write(): void
    {
        $user = User::factory()->create();

        UserPermission::syncForRole($user->id, Role::SYSTEM_ADMIN_READONLY);
        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertTrue($perms->every(fn ($p) => $p->can_write === false));

        UserPermission::syncForRole($user->id, Role::SYSTEM_ADMIN);
        $perms = UserPermission::where('user_id', $user->id)->get();
        $this->assertTrue($perms->every(fn ($p) => $p->can_write === true));
    }

    public function test_sync_for_role_modules_match_all_modules_constant(): void
    {
        $expected = ['feed', 'contracts', 'repository', 'processes', 'accounting', 'inventory', 'tracking', 'catalog', 'calendar'];
        $this->assertEqualsCanonicalizing($expected, UserPermission::ALL_MODULES);
    }

    // ===========================================================================
    // 2. POST /feed/posts — SYSTEM_ADMIN and SYSTEM_ADMIN_READONLY
    // ===========================================================================

    public function test_system_admin_can_create_feed_post(): void
    {
        $user = $this->createUserWithRole(Role::SYSTEM_ADMIN);
        UserPermission::syncForRole($user->id, Role::SYSTEM_ADMIN);

        $response = $this->actingAs($user)->postJson('/api/v1/feed/posts', [
            'title' => 'System Admin Post',
            'body' => 'Content from system admin.',
            'type' => 'announcement',
            'visibility' => 'global',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }

    public function test_system_admin_readonly_cannot_create_feed_post(): void
    {
        $user = $this->createUserWithRole(Role::SYSTEM_ADMIN_READONLY);
        UserPermission::syncForRole($user->id, Role::SYSTEM_ADMIN_READONLY);

        $response = $this->actingAs($user)->postJson('/api/v1/feed/posts', [
            'title' => 'Readonly Post',
            'body' => 'Should be blocked.',
            'type' => 'announcement',
            'visibility' => 'global',
        ]);

        $response->assertStatus(403);
    }

    public function test_system_admin_can_read_feed_posts(): void
    {
        $user = $this->createUserWithRole(Role::SYSTEM_ADMIN);
        UserPermission::syncForRole($user->id, Role::SYSTEM_ADMIN);

        $response = $this->actingAs($user)->getJson('/api/v1/feed/posts');

        $response->assertStatus(200);
    }

    public function test_system_admin_readonly_can_read_feed_posts(): void
    {
        $user = $this->createUserWithRole(Role::SYSTEM_ADMIN_READONLY);
        UserPermission::syncForRole($user->id, Role::SYSTEM_ADMIN_READONLY);

        $response = $this->actingAs($user)->getJson('/api/v1/feed/posts');

        $response->assertStatus(200);
    }

    // ===========================================================================
    // 3. GET /invitations — each role that has access
    // ===========================================================================

    public function test_superadmin_can_list_invitations(): void
    {
        $user = $this->createUserWithRole(Role::SUPERADMIN);

        $response = $this->actingAs($user)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_system_admin_can_list_invitations(): void
    {
        $user = $this->createUserWithRole(Role::SYSTEM_ADMIN);

        $response = $this->actingAs($user)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_system_admin_readonly_can_list_invitations(): void
    {
        $user = $this->createUserWithRole(Role::SYSTEM_ADMIN_READONLY);

        $response = $this->actingAs($user)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_admin_sm_can_list_invitations(): void
    {
        $franchise = Franchise::factory()->create();
        $user = $this->createUserWithRole(Role::ADMIN_SM, ['sm_franchise_id' => $franchise->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_sb_owner_can_list_invitations(): void
    {
        $franchise = Franchise::factory()->create();
        $company = Company::create([
            'name' => 'Test Company',
            'franchise_id' => $franchise->id,
            'sm_franchise_id' => $franchise->id,
        ]);
        $user = $this->createUserWithRole(Role::SB_OWNER, ['company_id' => $company->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_sb_employee_cannot_list_invitations(): void
    {
        $user = $this->createUserWithRole(Role::SB_EMPLOYEE);

        $response = $this->actingAs($user)->getJson('/api/v1/invitations');

        $response->assertStatus(403);
    }

    public function test_bb_employee_cannot_list_invitations(): void
    {
        $user = $this->createUserWithRole(Role::BB_EMPLOYEE);

        $response = $this->actingAs($user)->getJson('/api/v1/invitations');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_list_invitations(): void
    {
        $response = $this->getJson('/api/v1/invitations');

        $response->assertStatus(401);
    }
}

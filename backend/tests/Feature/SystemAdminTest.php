<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class SystemAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatieRole::firstOrCreate(['name' => Role::SUPERADMIN, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::SYSTEM_ADMIN, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::SYSTEM_ADMIN_READONLY, 'guard_name' => 'web']);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Create a user with the superadmin Spatie role.
     */
    private function createSuperadmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::SUPERADMIN);

        return $user;
    }

    /**
     * Create a user with the system_admin Spatie role.
     */
    private function createSystemAdmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::SYSTEM_ADMIN);

        return $user;
    }

    /**
     * Create a user with the system_admin_readonly Spatie role.
     */
    private function createSystemAdminReadonly(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::SYSTEM_ADMIN_READONLY);

        return $user;
    }

    /**
     * Create a user with no specific role.
     */
    private function createRegularUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Default valid payload for creating a system admin.
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Admin',
            'email' => 'testadmin@example.com',
            'password' => 'securepass12345',
            'role' => Role::SYSTEM_ADMIN,
        ], $overrides);
    }

    // ===========================================================================
    // GET /api/v1/system-admins  (index)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_system_admin_index(): void
    {
        $response = $this->getJson('/api/v1/system-admins');

        $response->assertStatus(401);
    }

    public function test_superadmin_can_list_system_admins(): void
    {
        $superadmin = $this->createSuperadmin();
        $this->createSystemAdmin(['email' => 'admin1@test.com']);
        $this->createSystemAdminReadonly(['email' => 'readonly1@test.com']);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/system-admins');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(2, 'data');
    }

    public function test_system_admin_index_returns_correct_json_structure(): void
    {
        $superadmin = $this->createSuperadmin();
        $this->createSystemAdmin(['name' => 'Admin Uno', 'email' => 'adminuno@test.com']);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/system-admins');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'roles',
                ],
            ],
        ]);
    }

    public function test_system_admin_index_includes_roles_relation(): void
    {
        $superadmin = $this->createSuperadmin();
        $this->createSystemAdmin(['email' => 'withrole@test.com']);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/system-admins');

        $response->assertStatus(200);
        $roleName = $response->json('data.0.roles.0.name');
        $this->assertSame(Role::SYSTEM_ADMIN, $roleName);
    }

    public function test_system_admin_index_does_not_include_superadmin_accounts(): void
    {
        $superadmin = $this->createSuperadmin();
        $this->createSystemAdmin(['email' => 'sysadmin@test.com']);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/system-admins');

        $response->assertStatus(200);
        // Only the system_admin, NOT the superadmin who made the request
        $response->assertJsonCount(1, 'data');
    }

    public function test_regular_user_is_forbidden_from_system_admin_index(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/system-admins');

        $response->assertStatus(403);
    }

    public function test_system_admin_is_forbidden_from_system_admin_index(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/system-admins');

        $response->assertStatus(403);
    }

    public function test_system_admin_readonly_is_forbidden_from_system_admin_index(): void
    {
        $readonly = $this->createSystemAdminReadonly();

        $response = $this->actingAs($readonly)->getJson('/api/v1/system-admins');

        $response->assertStatus(403);
    }

    // ===========================================================================
    // POST /api/v1/system-admins  (store)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_system_admin_store(): void
    {
        $response = $this->postJson('/api/v1/system-admins', $this->validPayload());

        $response->assertStatus(401);
    }

    public function test_superadmin_can_create_system_admin(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'system_admin.created_success');
        $response->assertJsonPath('data.name', 'Test Admin');
        $response->assertJsonPath('data.email', 'testadmin@example.com');

        $this->assertDatabaseHas('users', [
            'name' => 'Test Admin',
            'email' => 'testadmin@example.com',
        ]);
    }

    public function test_superadmin_can_create_system_admin_readonly(): void
    {
        $superadmin = $this->createSuperadmin();

        $payload = $this->validPayload([
            'email' => 'readonly@test.com',
            'role' => Role::SYSTEM_ADMIN_READONLY,
        ]);

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $payload);

        $response->assertStatus(201);

        $user = User::where('email', 'readonly@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(Role::SYSTEM_ADMIN_READONLY));
    }

    public function test_created_system_admin_has_role_assigned(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload());

        $user = User::where('email', 'testadmin@example.com')->first();
        $this->assertTrue($user->hasRole(Role::SYSTEM_ADMIN));
    }

    public function test_created_system_admin_response_includes_roles(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload());

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'roles'],
        ]);
    }

    public function test_created_system_admin_gets_all_module_permissions_with_write(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload([
                'role' => Role::SYSTEM_ADMIN,
            ]));

        $user = User::where('email', 'testadmin@example.com')->first();
        $permissions = UserPermission::where('user_id', $user->id)->get();

        $expectedModules = ['feed', 'contracts', 'repository', 'processes', 'accounting', 'inventory', 'tracking', 'catalog', 'calendar'];
        $this->assertCount(count($expectedModules), $permissions);
        $this->assertEqualsCanonicalizing($expectedModules, $permissions->pluck('module')->all());
        $this->assertTrue($permissions->every(fn ($p) => $p->can_read === true));
        $this->assertTrue($permissions->every(fn ($p) => $p->can_write === true));
    }

    public function test_created_system_admin_readonly_gets_read_only_permissions(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload([
                'email' => 'readonlyperm@test.com',
                'role' => Role::SYSTEM_ADMIN_READONLY,
            ]));

        $user = User::where('email', 'readonlyperm@test.com')->first();
        $permissions = UserPermission::where('user_id', $user->id)->get();

        $expectedModules = ['feed', 'contracts', 'repository', 'processes', 'accounting', 'inventory', 'tracking', 'catalog', 'calendar'];
        $this->assertCount(count($expectedModules), $permissions);
        $this->assertEqualsCanonicalizing($expectedModules, $permissions->pluck('module')->all());
        $this->assertTrue($permissions->every(fn ($p) => $p->can_read === true));
        $this->assertTrue($permissions->every(fn ($p) => $p->can_write === false));
    }

    public function test_password_is_hashed_when_system_admin_is_created(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload([
                'password' => 'securepass12345',
            ]));

        $user = User::where('email', 'testadmin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('securepass12345', $user->password));
        // Must NOT be stored as plain text
        $this->assertNotSame('securepass12345', $user->password);
    }

    // --- Validation: store ---

    public function test_store_system_admin_validates_required_name(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload(['name' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_system_admin_validates_required_email(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload(['email' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_store_system_admin_validates_email_format(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload(['email' => 'not-an-email']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_store_system_admin_validates_unique_email(): void
    {
        $superadmin = $this->createSuperadmin();
        User::factory()->create(['email' => 'existing@test.com']);

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload(['email' => 'existing@test.com']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_store_system_admin_validates_required_password(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload(['password' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_store_system_admin_validates_password_min_12_chars(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload(['password' => 'short12345']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_store_system_admin_accepts_password_exactly_12_chars(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload([
                'password' => '123456789012', // exactly 12 chars
            ]));

        $response->assertStatus(201);
    }

    public function test_store_system_admin_validates_role_must_be_system_admin_or_readonly(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload(['role' => 'superadmin']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    public function test_store_system_admin_rejects_unknown_role(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/system-admins', $this->validPayload(['role' => 'hacker']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    // --- Authorization: store ---

    public function test_regular_user_cannot_create_system_admin(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/system-admins', $this->validPayload());

        $response->assertStatus(403);
    }

    public function test_system_admin_cannot_create_another_system_admin(): void
    {
        $admin = $this->createSystemAdmin(['email' => 'existing_admin@test.com']);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/system-admins', $this->validPayload(['email' => 'new_admin@test.com']));

        $response->assertStatus(403);
    }

    // ===========================================================================
    // PUT /api/v1/system-admins/{system_admin}  (update)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_system_admin_update(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->putJson("/api/v1/system-admins/{$admin->id}", [
            'name' => 'Updated',
            'email' => $admin->email,
            'role' => Role::SYSTEM_ADMIN,
        ]);

        $response->assertStatus(401);
    }

    public function test_superadmin_can_update_system_admin(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createSystemAdmin(['email' => 'before@test.com']);

        $response = $this->actingAs($superadmin)
            ->putJson("/api/v1/system-admins/{$admin->id}", [
                'name' => 'Updated Name',
                'email' => 'after@test.com',
                'role' => Role::SYSTEM_ADMIN,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'system_admin.updated_success');
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.email', 'after@test.com');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Updated Name',
            'email' => 'after@test.com',
        ]);
    }

    public function test_update_can_change_role_from_admin_to_readonly(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createSystemAdmin(['email' => 'demote@test.com']);

        $this->actingAs($superadmin)
            ->putJson("/api/v1/system-admins/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => Role::SYSTEM_ADMIN_READONLY,
            ]);

        $admin->refresh();
        $this->assertTrue($admin->hasRole(Role::SYSTEM_ADMIN_READONLY));
        $this->assertFalse($admin->hasRole(Role::SYSTEM_ADMIN));
    }

    public function test_update_readonly_to_admin_upgrades_permissions_to_write(): void
    {
        $superadmin = $this->createSuperadmin();
        $readonly = $this->createSystemAdminReadonly(['email' => 'promote@test.com']);

        $this->actingAs($superadmin)
            ->putJson("/api/v1/system-admins/{$readonly->id}", [
                'name' => $readonly->name,
                'email' => $readonly->email,
                'role' => Role::SYSTEM_ADMIN,
            ]);

        $permissions = UserPermission::where('user_id', $readonly->id)->get();
        $this->assertTrue($permissions->every(fn ($p) => $p->can_write === true));
    }

    public function test_update_password_is_optional(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createSystemAdmin(['email' => 'nopwchange@test.com']);
        $originalHash = $admin->password;

        $response = $this->actingAs($superadmin)
            ->putJson("/api/v1/system-admins/{$admin->id}", [
                'name' => 'No Password Change',
                'email' => $admin->email,
                'role' => Role::SYSTEM_ADMIN,
            ]);

        $response->assertStatus(200);
        $admin->refresh();
        $this->assertSame($originalHash, $admin->password);
    }

    public function test_update_password_changes_when_provided(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createSystemAdmin(['email' => 'pwchange@test.com']);

        $this->actingAs($superadmin)
            ->putJson("/api/v1/system-admins/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'password' => 'newsecurepassword99',
                'role' => Role::SYSTEM_ADMIN,
            ]);

        $admin->refresh();
        $this->assertTrue(Hash::check('newsecurepassword99', $admin->password));
    }

    public function test_update_rejects_short_password_when_provided(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createSystemAdmin(['email' => 'shortpw@test.com']);

        $response = $this->actingAs($superadmin)
            ->putJson("/api/v1/system-admins/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'password' => 'short',
                'role' => Role::SYSTEM_ADMIN,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_update_response_includes_roles(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createSystemAdmin(['email' => 'rolecheck@test.com']);

        $response = $this->actingAs($superadmin)
            ->putJson("/api/v1/system-admins/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => Role::SYSTEM_ADMIN,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'roles'],
        ]);
    }

    public function test_regular_user_cannot_update_system_admin(): void
    {
        $user = $this->createRegularUser();
        $admin = $this->createSystemAdmin(['email' => 'target@test.com']);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/system-admins/{$admin->id}", [
                'name' => 'Hacked',
                'email' => $admin->email,
                'role' => Role::SYSTEM_ADMIN,
            ]);

        $response->assertStatus(403);
    }

    // ===========================================================================
    // DELETE /api/v1/system-admins/{system_admin}  (destroy)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_system_admin_delete(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->deleteJson("/api/v1/system-admins/{$admin->id}");

        $response->assertStatus(401);
    }

    public function test_superadmin_can_delete_system_admin(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createSystemAdmin(['email' => 'todelete@test.com']);
        $adminId = $admin->id;

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/system-admins/{$adminId}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'system_admin.deleted_success');

        $this->assertSoftDeleted('users', ['id' => $adminId]);
    }

    public function test_delete_also_removes_user_permissions(): void
    {
        $superadmin = $this->createSuperadmin();
        $admin = $this->createSystemAdmin(['email' => 'withperms@test.com']);

        // Simulate having module permissions
        UserPermission::create([
            'user_id' => $admin->id,
            'module' => 'feed',
            'can_read' => true,
            'can_write' => true,
        ]);

        $this->actingAs($superadmin)
            ->deleteJson("/api/v1/system-admins/{$admin->id}");

        $this->assertDatabaseMissing('user_permissions', ['user_id' => $admin->id]);
    }

    public function test_superadmin_cannot_delete_themselves(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/system-admins/{$superadmin->id}");

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_delete_system_admin(): void
    {
        $user = $this->createRegularUser();
        $admin = $this->createSystemAdmin(['email' => 'safedelete@test.com']);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/system-admins/{$admin->id}");

        $response->assertStatus(403);
    }

    public function test_system_admin_cannot_delete_another_system_admin(): void
    {
        $admin1 = $this->createSystemAdmin(['email' => 'admin1@test.com']);
        $admin2 = $this->createSystemAdmin(['email' => 'admin2@test.com']);

        $response = $this->actingAs($admin1)
            ->deleteJson("/api/v1/system-admins/{$admin2->id}");

        $response->assertStatus(403);
    }
}

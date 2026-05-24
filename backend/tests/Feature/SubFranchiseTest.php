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

class SubFranchiseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatieRole::firstOrCreate(['name' => Role::SUPERADMIN, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::SYSTEM_ADMIN, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::SB_OWNER, 'guard_name' => 'web']);
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

    private function createSbOwner(Franchise $franchise, Company $company, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'sm_franchise_id' => $franchise->id,
            'company_id' => $company->id,
        ], $attrs));
        $user->assignRole(Role::SB_OWNER);
        UserPermission::syncForRole($user->id, Role::SB_OWNER);

        return $user;
    }

    private function createCompany(Franchise $franchise): Company
    {
        return Company::create([
            'name' => 'Test Company '.uniqid(),
            'sm_franchise_id' => $franchise->id,
        ]);
    }

    private function validSubPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'My Sub-franchise '.uniqid(),
        ], $overrides);
    }

    // ===========================================================================
    // POST /api/v1/franchises/sub
    // ===========================================================================

    public function test_sb_owner_can_create_sub_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $sbOwner = $this->createSbOwner($franchise, $company);

        $response = $this->actingAs($sbOwner)
            ->postJson('/api/v1/franchises/sub', $this->validSubPayload());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }

    public function test_sub_franchise_has_correct_type_and_parent(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $sbOwner = $this->createSbOwner($franchise, $company);

        $this->actingAs($sbOwner)
            ->postJson('/api/v1/franchises/sub', ['name' => 'My Sub']);

        $this->assertDatabaseHas('franchises', [
            'name' => 'My Sub',
            'type' => 'sub',
            'parent_company_id' => $company->id,
        ]);
    }

    public function test_sb_owner_cannot_create_sm_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $sbOwner = $this->createSbOwner($franchise, $company);

        // Even if they try to override type, the request forces type='sub'
        // and routes to storeSub which calls createSub policy.
        // The main SM creation route is POST /franchises (not /franchises/sub)
        // which blocks sb_owner via the create() policy.
        $response = $this->actingAs($sbOwner)
            ->postJson('/api/v1/franchises', [
                'name' => 'Trying SM',
                'type' => 'sm',
                'country' => 'US',
                'timezone' => 'America/New_York',
                'phone' => '+1111111111',
                'address' => '123 Main St',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_sm_cannot_create_sub_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/franchises/sub', $this->validSubPayload());

        $response->assertStatus(403);
    }

    public function test_superadmin_can_create_sub_franchise(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/franchises/sub', array_merge(
                $this->validSubPayload(),
                ['parent_company_id' => $company->id, 'owner_user_id' => $superadmin->id]
            ));

        $response->assertStatus(201);
    }

    public function test_sub_franchise_requires_name(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $sbOwner = $this->createSbOwner($franchise, $company);

        $response = $this->actingAs($sbOwner)
            ->postJson('/api/v1/franchises/sub', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    // ===========================================================================
    // GET /api/v1/franchises  (listing, scoped by role)
    // ===========================================================================

    public function test_sb_owner_sees_only_own_company_sub_franchises(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $sbOwner = $this->createSbOwner($franchise, $company);

        // Own sub-franchise
        Franchise::create(['name' => 'Own Sub', 'type' => 'sub', 'parent_company_id' => $company->id]);
        // Other company's sub-franchise (should not be visible)
        $otherCompany = $this->createCompany($franchise);
        Franchise::create(['name' => 'Other Sub', 'type' => 'sub', 'parent_company_id' => $otherCompany->id]);
        // SM franchise (should not be visible)
        Franchise::factory()->create(['type' => 'sm']);

        $response = $this->actingAs($sbOwner)
            ->getJson('/api/v1/franchises');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Own Sub'));
        $this->assertFalse($names->contains('Other Sub'));
        $this->assertFalse($names->contains($franchise->name));
    }

    public function test_sb_owner_cannot_see_sm_franchises_in_listing(): void
    {
        $franchise = Franchise::factory()->create(['type' => 'sm']);
        $company = $this->createCompany($franchise);
        $sbOwner = $this->createSbOwner($franchise, $company);

        Franchise::factory()->create(['type' => 'sm']); // another SM franchise

        $response = $this->actingAs($sbOwner)
            ->getJson('/api/v1/franchises');

        $response->assertStatus(200);
        $types = collect($response->json('data'))->pluck('type');
        $this->assertTrue($types->filter(fn ($t) => $t === 'sm')->isEmpty());
    }
}

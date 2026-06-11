<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Franchise;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class RepositoryTest extends TestCase
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
     * Create a plain user with no roles assigned.
     */
    private function createRegularUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Create a Company belonging to the given franchise.
     */
    private function makeCompany(Franchise $franchise, array $attributes = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Test Company',
            'sm_franchise_id' => $franchise->id,
        ], $attributes));
    }

    /**
     * Create a Repository for the given company.
     */
    private function makeRepository(Company $company, array $attributes = []): Repository
    {
        return Repository::create(array_merge([
            'company_id' => $company->id,
        ], $attributes));
    }

    // ===========================================================================
    // GET /api/v1/repositories  (index)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_repository_index(): void
    {
        $response = $this->getJson('/api/v1/repositories');

        $response->assertStatus(401);
    }

    public function test_regular_user_gets_403_on_repository_index(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/repositories');

        $response->assertStatus(403);
    }

    public function test_superadmin_can_list_all_repositories(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchiseA = Franchise::factory()->create();
        $franchiseB = Franchise::factory()->create();
        $companyA = $this->makeCompany($franchiseA, ['name' => 'Company A']);
        $companyB = $this->makeCompany($franchiseB, ['name' => 'Company B']);
        $this->makeRepository($companyA);
        $this->makeRepository($companyB);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/repositories');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_sm_sees_only_repositories_of_their_franchise(): void
    {
        $myFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $myCompany = $this->makeCompany($myFranchise, ['name' => 'My Company']);
        $otherCompany = $this->makeCompany($otherFranchise, ['name' => 'Other Company']);
        $this->makeRepository($myCompany);
        $this->makeRepository($otherCompany);

        $admin = $this->createAdminSm($myFranchise);

        $response = $this->actingAs($admin)->getJson('/api/v1/repositories');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.company.name', 'My Company');
    }

    public function test_repository_index_returns_correct_json_structure(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);
        $this->makeRepository($company);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/repositories');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'company',
                    'documents_count',
                    'created_at',
                ],
            ],
        ]);
    }

    // ===========================================================================
    // POST /api/v1/repositories  (store)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_repository_store(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);

        $response = $this->postJson('/api/v1/repositories', [
            'company_id' => $company->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_regular_user_gets_403_on_repository_store(): void
    {
        $user = $this->createRegularUser();
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);

        $response = $this->actingAs($user)->postJson('/api/v1/repositories', [
            'company_id' => $company->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_create_repository_with_valid_company_id(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);

        $response = $this->actingAs($superadmin)->postJson('/api/v1/repositories', [
            'company_id' => $company->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }

    public function test_store_repository_persists_to_database(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);

        $this->actingAs($superadmin)->postJson('/api/v1/repositories', [
            'company_id' => $company->id,
        ]);

        $this->assertDatabaseHas('repositories', [
            'company_id' => $company->id,
        ]);
    }

    public function test_store_returns_422_when_company_id_is_missing(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/repositories', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['company_id']);
    }

    public function test_store_returns_422_when_company_id_does_not_exist_in_db(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/repositories', [
            'company_id' => 999999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['company_id']);
    }

    // ===========================================================================
    // GET /api/v1/repositories/{id}  (show)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_repository_show(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $response = $this->getJson("/api/v1/repositories/{$repository->id}");

        $response->assertStatus(401);
    }

    public function test_superadmin_can_view_any_repository(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $response = $this->actingAs($superadmin)->getJson("/api/v1/repositories/{$repository->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $repository->id);
    }

    public function test_admin_sm_can_view_repository_of_their_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $admin = $this->createAdminSm($franchise);

        $response = $this->actingAs($admin)->getJson("/api/v1/repositories/{$repository->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $repository->id);
    }

    public function test_admin_sm_cannot_view_repository_of_another_franchise(): void
    {
        $myFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $otherCompany = $this->makeCompany($otherFranchise);
        $otherRepository = $this->makeRepository($otherCompany);
        $admin = $this->createAdminSm($myFranchise);

        $response = $this->actingAs($admin)->getJson("/api/v1/repositories/{$otherRepository->id}");

        $response->assertStatus(403);
    }

    public function test_show_returns_404_for_nonexistent_repository_id(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/repositories/999999');

        $response->assertStatus(404);
    }

    // ===========================================================================
    // DELETE /api/v1/repositories/{id}  (destroy)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_repository_destroy(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $response = $this->deleteJson("/api/v1/repositories/{$repository->id}");

        $response->assertStatus(401);
    }

    public function test_regular_user_gets_403_on_repository_destroy(): void
    {
        $user = $this->createRegularUser();
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $response = $this->actingAs($user)->deleteJson("/api/v1/repositories/{$repository->id}");

        $response->assertStatus(403);
    }

    public function test_superadmin_can_delete_repository(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $repositoryId = $repository->id;

        $response = $this->actingAs($superadmin)->deleteJson("/api/v1/repositories/{$repositoryId}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_deleted_repository_is_removed_from_database(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $repositoryId = $repository->id;

        $this->actingAs($superadmin)->deleteJson("/api/v1/repositories/{$repositoryId}");

        $this->assertDatabaseMissing('repositories', ['id' => $repositoryId]);
    }

    public function test_destroy_returns_404_for_nonexistent_repository_id(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->deleteJson('/api/v1/repositories/999999');

        $response->assertStatus(404);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Franchise;
use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\ProcessMap;
use App\Models\Repository;
use App\Models\SubProcess;
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

    /**
     * Create a full process tree (map → category → process → subprocess) linked
     * to the given company and return the ProcessMap.
     *
     * @return array{map: ProcessMap, category: ProcessCategory, process: Process, subProcess: SubProcess}
     */
    private function makeProcessTree(Company $company, string $mapType = 'franquiciadora'): array
    {
        $map = ProcessMap::factory()->forCompany($company)->create([
            'type' => $mapType,
        ]);

        $category = ProcessCategory::factory()->create([
            'process_map_id' => $map->id,
        ]);

        $process = Process::factory()->create([
            'category_id' => $category->id,
        ]);

        $subProcess = SubProcess::factory()->create([
            'process_id' => $process->id,
        ]);

        return compact('map', 'category', 'process', 'subProcess');
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

        $response->assertNoContent();
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

    // ===========================================================================
    // GET /api/v1/repositories/{id}/process-documents  (processDocuments)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_process_documents(): void
    {
        $franchise = Franchise::factory()->sm()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $this->getJson("/api/v1/repositories/{$repository->id}/process-documents")
            ->assertStatus(401);
    }

    public function test_regular_user_gets_403_on_process_documents(): void
    {
        $franchise = Franchise::factory()->sm()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $user = $this->createRegularUser();

        $this->actingAs($user)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents")
            ->assertStatus(403);
    }

    public function test_admin_sm_from_different_franchise_gets_403_on_process_documents(): void
    {
        $myFranchise = Franchise::factory()->sm()->create(['name' => 'My Franchise']);
        $otherFranchise = Franchise::factory()->sm()->create(['name' => 'Other Franchise']);
        $otherCompany = $this->makeCompany($otherFranchise);
        $otherRepository = $this->makeRepository($otherCompany);
        $admin = $this->createAdminSm($myFranchise);

        $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$otherRepository->id}/process-documents")
            ->assertStatus(403);
    }

    public function test_process_documents_returns_404_for_nonexistent_repository(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->getJson('/api/v1/repositories/999999/process-documents')
            ->assertStatus(404);
    }

    public function test_process_documents_returns_200_with_null_data_when_no_process_map(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->sm()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        // Intentionally no ProcessMap created for this company.

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $response->assertStatus(200)
            ->assertExactJson(['data' => null]);
    }

    public function test_superadmin_gets_process_tree_structure_when_franquiciadora_map_exists(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->sm()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        ['map' => $map] = $this->makeProcessTree($company, 'franquiciadora');

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $response->assertStatus(200)
            ->assertJsonPath('data.process_map_id', $map->id)
            ->assertJsonStructure([
                'data' => [
                    'process_map_id',
                    'categories',
                ],
            ]);

        // The categories array must contain at least the one we created.
        $this->assertCount(1, $response->json('data.categories'));
    }

    public function test_falls_back_to_franquiciada_map_when_no_franquiciadora_exists(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->sm()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        // Only a franquiciada map exists — endpoint falls back to it instead of returning null.
        ['map' => $map] = $this->makeProcessTree($company, 'franquiciada');

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $response->assertStatus(200)
            ->assertJsonPath('data.process_map_id', $map->id);
    }

    public function test_admin_sm_from_same_franchise_can_access_process_documents(): void
    {
        $franchise = Franchise::factory()->sm()->create();
        $admin = $this->createAdminSm($franchise);
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        ['map' => $map] = $this->makeProcessTree($company, 'franquiciadora');

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $response->assertStatus(200)
            ->assertJsonPath('data.process_map_id', $map->id);
    }

    public function test_process_documents_nests_subprocess_documents_in_tree(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->sm()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        ['subProcess' => $subProcess] = $this->makeProcessTree($company, 'franquiciadora');

        // Create via the morphMany relationship — documentable_type/id are set by Eloquent.
        $subProcess->documents()->create([
            'code' => 'DOC-001',
            'type' => 'MN',
            'title_es' => 'Manual de prueba',
            'title_en' => 'Test manual',
            'version' => 1,
            'is_current' => true,
            'uploaded_by' => $superadmin->id,
        ]);

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $response->assertStatus(200);

        // Verify the document is nested inside the tree path:
        // data → categories[0] → processes[0] → sub_processes[0] → documents[0]
        $subProcesses = $response->json('data.categories.0.processes.0.sub_processes');
        $this->assertNotEmpty($subProcesses);
        $documents = $subProcesses[0]['documents'];
        $this->assertCount(1, $documents);
        $this->assertEquals('DOC-001', $documents[0]['code']);
    }

    public function test_soft_deleted_process_documents_are_excluded_from_tree(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->sm()->create();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        ['subProcess' => $subProcess] = $this->makeProcessTree($company, 'franquiciadora');

        $doc = $subProcess->documents()->create([
            'code' => 'DOC-DEL',
            'type' => 'MN',
            'title_es' => 'Documento eliminado',
            'title_en' => 'Deleted document',
            'version' => 1,
            'is_current' => true,
            'uploaded_by' => $superadmin->id,
        ]);
        $doc->delete(); // soft-delete

        $response = $this->actingAs($superadmin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $response->assertStatus(200);
        $subProcesses = $response->json('data.categories.0.processes.0.sub_processes');
        $documents = $subProcesses[0]['documents'];
        $this->assertEmpty($documents, 'Soft-deleted documents must not appear in the tree');
    }
}

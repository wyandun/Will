<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Franchise;
use App\Models\Process;
use App\Models\ProcessCategory;
use App\Models\ProcessDocument;
use App\Models\ProcessMap;
use App\Models\Repository;
use App\Models\SubProcess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class ProcessDocumentTreeTest extends TestCase
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
        $user = User::factory()->create(array_merge(['sm_franchise_id' => $franchise->id], $attributes));
        $user->assignRole(Role::ADMIN_SM);

        return $user;
    }

    private function createRegularUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function makeFranchise(array $attributes = []): Franchise
    {
        return Franchise::create(array_merge(['name' => 'SM Franchise'], $attributes));
    }

    private function makeCompany(Franchise $franchise, array $attributes = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Test Company',
            'sm_franchise_id' => $franchise->id,
        ], $attributes));
    }

    private function makeRepository(Company $company): Repository
    {
        return Repository::create(['company_id' => $company->id]);
    }

    private function makeProcessMap(Company $company, bool $active = true): ProcessMap
    {
        return ProcessMap::create([
            'company_id' => $company->id,
            'type' => 'franquiciadora',
            'name_es' => 'Mapa de Procesos',
            'name_en' => 'Process Map',
            'is_active' => $active,
        ]);
    }

    private function makeCategory(ProcessMap $map, string $type = 'strategic'): ProcessCategory
    {
        return ProcessCategory::create([
            'process_map_id' => $map->id,
            'type' => $type,
            'name_es' => ucfirst($type),
            'name_en' => ucfirst($type),
            'order_index' => 0,
        ]);
    }

    private function makeProcess(ProcessCategory $category, string $code = 'CC'): Process
    {
        return Process::create([
            'category_id' => $category->id,
            'code' => $code,
            'name_es' => 'Control de Calidad',
            'name_en' => 'Quality Control',
            'order_index' => 0,
        ]);
    }

    private function makeSubProcess(Process $process, string $code = 'CC-P01'): SubProcess
    {
        return SubProcess::create([
            'process_id' => $process->id,
            'code' => $code,
            'name_es' => 'Gestión de riesgos',
            'name_en' => 'Risk Management',
            'order_index' => 0,
        ]);
    }

    private function makeProcessDocument(SubProcess $sub, User $uploader, array $attributes = []): ProcessDocument
    {
        return ProcessDocument::create(array_merge([
            'documentable_type' => SubProcess::class,
            'documentable_id' => $sub->id,
            'code' => 'CC-P01-FOR-01',
            'type' => 'FOR',
            'title_es' => 'Matriz de Riesgos',
            'title_en' => 'Risk Matrix',
            'file_url' => 'https://example.com/files/matrix.pdf',
            'version' => 1,
            'is_current' => true,
            'uploaded_by' => $uploader->id,
        ], $attributes));
    }

    // ===========================================================================
    // GET /api/v1/repositories/{id}/process-documents
    // ===========================================================================

    public function test_unauthenticated_user_gets_401(): void
    {
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $this->getJson("/api/v1/repositories/{$repository->id}/process-documents")
            ->assertStatus(401);
    }

    public function test_regular_user_gets_403(): void
    {
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $user = $this->createRegularUser();

        $this->actingAs($user)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents")
            ->assertStatus(403);
    }

    public function test_returns_null_data_when_no_active_process_map(): void
    {
        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_superadmin_gets_full_process_document_tree(): void
    {
        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $map = $this->makeProcessMap($company);
        $category = $this->makeCategory($map, 'strategic');
        $process = $this->makeProcess($category, 'CC');
        $sub = $this->makeSubProcess($process, 'CC-P01');
        $this->makeProcessDocument($sub, $admin);
        $this->makeProcessDocument($sub, $admin, [
            'code' => 'CC-P01-MP-01',
            'type' => 'MP',
            'title_es' => 'Manual de Proceso',
            'title_en' => 'Process Manual',
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.process_map_id', $map->id)
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.type', 'strategic')
            ->assertJsonPath('data.categories.0.docs_count', 2)
            ->assertJsonCount(1, 'data.categories.0.processes')
            ->assertJsonPath('data.categories.0.processes.0.code', 'CC')
            ->assertJsonCount(1, 'data.categories.0.processes.0.sub_processes')
            ->assertJsonCount(2, 'data.categories.0.processes.0.sub_processes.0.documents');
    }

    public function test_inactive_process_map_is_excluded(): void
    {
        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $this->makeProcessMap($company, false);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $response->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    public function test_admin_sm_cannot_see_process_documents_of_another_franchise(): void
    {
        $franchise1 = $this->makeFranchise(['name' => 'F1']);
        $franchise2 = $this->makeFranchise(['name' => 'F2']);
        $admin1 = $this->createAdminSm($franchise1);
        $company2 = $this->makeCompany($franchise2);
        $repo2 = $this->makeRepository($company2);

        $this->actingAs($admin1)
            ->getJson("/api/v1/repositories/{$repo2->id}/process-documents")
            ->assertStatus(403);
    }

    public function test_admin_sm_can_see_process_documents_of_own_franchise(): void
    {
        $franchise = $this->makeFranchise();
        $admin = $this->createAdminSm($franchise);
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $map = $this->makeProcessMap($company);
        $category = $this->makeCategory($map);
        $process = $this->makeProcess($category);
        $sub = $this->makeSubProcess($process);
        $this->makeProcessDocument($sub, $admin);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $response->assertStatus(200)
            ->assertJsonPath('data.categories.0.docs_count', 1);
    }

    public function test_non_current_documents_are_excluded(): void
    {
        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $map = $this->makeProcessMap($company);
        $category = $this->makeCategory($map);
        $process = $this->makeProcess($category);
        $sub = $this->makeSubProcess($process);

        $this->makeProcessDocument($sub, $admin, ['is_current' => true]);
        $this->makeProcessDocument($sub, $admin, ['is_current' => false, 'code' => 'CC-P01-FOR-02']);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.categories.0.processes.0.sub_processes.0.documents');
    }

    public function test_document_response_shape_is_correct(): void
    {
        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $map = $this->makeProcessMap($company);
        $category = $this->makeCategory($map);
        $process = $this->makeProcess($category);
        $sub = $this->makeSubProcess($process);
        $this->makeProcessDocument($sub, $admin);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$repository->id}/process-documents");

        $doc = $response->json('data.categories.0.processes.0.sub_processes.0.documents.0');
        $this->assertArrayHasKey('id', $doc);
        $this->assertArrayHasKey('code', $doc);
        $this->assertArrayHasKey('type', $doc);
        $this->assertArrayHasKey('title_es', $doc);
        $this->assertArrayHasKey('title_en', $doc);
        $this->assertArrayHasKey('version', $doc);
        $this->assertArrayHasKey('file_url', $doc);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Franchise;
use App\Models\Repository;
use App\Models\RepositoryDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class RepositoryDocumentTest extends TestCase
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

    private function makeRepository(Company $company, array $attributes = []): Repository
    {
        return Repository::create(array_merge([
            'company_id' => $company->id,
        ], $attributes));
    }

    private function makeDocument(Repository $repository, User $uploader, array $attributes = []): RepositoryDocument
    {
        return RepositoryDocument::create(array_merge([
            'repository_id' => $repository->id,
            'section' => 'setup',
            'setup_category' => 'legal',
            'title' => 'Test Document',
            'file_path' => 'repositories/1/setup/legal/test.pdf',
            'file_url' => 'http://localhost/storage/repositories/1/setup/legal/test.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_by' => $uploader->id,
            'uploaded_by_type' => 'sm',
            'version' => 1,
            'is_current' => true,
        ], $attributes));
    }

    // ===========================================================================
    // GET /api/v1/repositories/{id}/documents  (index)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_documents_index(): void
    {
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $this->getJson("/api/v1/repositories/{$repository->id}/documents")
            ->assertStatus(401);
    }

    public function test_regular_user_gets_403_on_documents_index(): void
    {
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $user = $this->createRegularUser();

        $this->actingAs($user)
            ->getJson("/api/v1/repositories/{$repository->id}/documents")
            ->assertStatus(403);
    }

    public function test_superadmin_can_list_setup_documents(): void
    {
        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $this->makeDocument($repository, $admin, ['setup_category' => 'legal']);
        $this->makeDocument($repository, $admin, ['setup_category' => 'hr']);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$repository->id}/documents?section=setup");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_superadmin_can_filter_documents_by_category(): void
    {
        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $this->makeDocument($repository, $admin, ['setup_category' => 'legal']);
        $this->makeDocument($repository, $admin, ['setup_category' => 'hr']);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$repository->id}/documents?section=setup&category=legal");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_sm_cannot_list_documents_of_another_franchise(): void
    {
        $franchise1 = $this->makeFranchise(['name' => 'Franchise 1']);
        $franchise2 = $this->makeFranchise(['name' => 'Franchise 2']);
        $admin = $this->createAdminSm($franchise1);
        $company2 = $this->makeCompany($franchise2);
        $repository2 = $this->makeRepository($company2);

        $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$repository2->id}/documents")
            ->assertStatus(403);
    }

    public function test_admin_sm_can_list_documents_of_own_franchise(): void
    {
        $franchise = $this->makeFranchise();
        $admin = $this->createAdminSm($franchise);
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $this->makeDocument($repository, $admin);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/repositories/{$repository->id}/documents?section=setup");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ===========================================================================
    // POST /api/v1/repositories/{id}/documents  (store)
    // ===========================================================================

    public function test_unauthenticated_user_cannot_upload_document(): void
    {
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $this->postJson("/api/v1/repositories/{$repository->id}/documents", [])
            ->assertStatus(401);
    }

    public function test_regular_user_gets_403_when_uploading_document(): void
    {
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $user = $this->createRegularUser();

        Storage::fake('public');

        $this->actingAs($user)
            ->postJson("/api/v1/repositories/{$repository->id}/documents", [
                'title' => 'Test',
                'section' => 'setup',
                'setup_category' => 'legal',
                'file' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(403);
    }

    public function test_superadmin_can_upload_document(): void
    {
        Storage::fake('public');

        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $file = UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf');

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/repositories/{$repository->id}/documents", [
                'title' => 'Articles of Incorporation',
                'description' => 'Company registration document',
                'section' => 'setup',
                'setup_category' => 'legal',
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Articles of Incorporation')
            ->assertJsonPath('data.setup_category', 'legal')
            ->assertJsonPath('data.uploaded_by_type', 'sm');

        $this->assertDatabaseHas('repository_documents', [
            'repository_id' => $repository->id,
            'title' => 'Articles of Incorporation',
            'section' => 'setup',
            'setup_category' => 'legal',
            'uploaded_by' => $admin->id,
        ]);
    }

    public function test_upload_fails_without_required_fields(): void
    {
        Storage::fake('public');

        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/repositories/{$repository->id}/documents", [
                'section' => 'setup',
                'setup_category' => 'legal',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'file']);
    }

    public function test_upload_fails_with_invalid_category(): void
    {
        Storage::fake('public');

        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/repositories/{$repository->id}/documents", [
                'title' => 'Test',
                'section' => 'setup',
                'setup_category' => 'invalid_category',
                'file' => UploadedFile::fake()->create('test.pdf', 10, 'application/pdf'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['setup_category']);
    }

    public function test_upload_fails_with_oversized_file(): void
    {
        Storage::fake('public');

        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/repositories/{$repository->id}/documents", [
                'title' => 'Big file',
                'section' => 'setup',
                'setup_category' => 'legal',
                'file' => UploadedFile::fake()->create('huge.pdf', 21000, 'application/pdf'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_admin_sm_cannot_upload_to_another_franchise_repository(): void
    {
        Storage::fake('public');

        $franchise1 = $this->makeFranchise(['name' => 'Franchise 1']);
        $franchise2 = $this->makeFranchise(['name' => 'Franchise 2']);
        $admin = $this->createAdminSm($franchise1);
        $company2 = $this->makeCompany($franchise2);
        $repository2 = $this->makeRepository($company2);

        $this->actingAs($admin)
            ->postJson("/api/v1/repositories/{$repository2->id}/documents", [
                'title' => 'Intruder',
                'section' => 'setup',
                'setup_category' => 'legal',
                'file' => UploadedFile::fake()->create('test.pdf', 10, 'application/pdf'),
            ])
            ->assertStatus(403);
    }

    // ===========================================================================
    // DELETE /api/v1/repositories/{id}/documents/{docId}  (destroy)
    // ===========================================================================

    public function test_unauthenticated_user_cannot_delete_document(): void
    {
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $uploader = $this->createSuperadmin();
        $doc = $this->makeDocument($repository, $uploader);

        $this->deleteJson("/api/v1/repositories/{$repository->id}/documents/{$doc->id}")
            ->assertStatus(401);
    }

    public function test_regular_user_cannot_delete_document(): void
    {
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $uploader = $this->createSuperadmin();
        $doc = $this->makeDocument($repository, $uploader);
        $user = $this->createRegularUser();

        $this->actingAs($user)
            ->deleteJson("/api/v1/repositories/{$repository->id}/documents/{$doc->id}")
            ->assertStatus(403);
    }

    public function test_superadmin_can_delete_document(): void
    {
        Storage::fake('public');

        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repository = $this->makeRepository($company);
        $doc = $this->makeDocument($repository, $admin);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/repositories/{$repository->id}/documents/{$doc->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('repository_documents', ['id' => $doc->id]);
    }

    public function test_cannot_delete_document_belonging_to_different_repository(): void
    {
        Storage::fake('public');

        $admin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $repo1 = $this->makeRepository($company);
        $repo2 = $this->makeRepository($company);
        $doc = $this->makeDocument($repo2, $admin);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/repositories/{$repo1->id}/documents/{$doc->id}")
            ->assertStatus(404);
    }

    public function test_admin_sm_cannot_delete_document_from_another_franchise(): void
    {
        Storage::fake('public');

        $franchise1 = $this->makeFranchise(['name' => 'Franchise 1']);
        $franchise2 = $this->makeFranchise(['name' => 'Franchise 2']);
        $superadmin = $this->createSuperadmin();
        $admin1 = $this->createAdminSm($franchise1);
        $company2 = $this->makeCompany($franchise2);
        $repo2 = $this->makeRepository($company2);
        $doc = $this->makeDocument($repo2, $superadmin);

        $this->actingAs($admin1)
            ->deleteJson("/api/v1/repositories/{$repo2->id}/documents/{$doc->id}")
            ->assertStatus(403);
    }
}

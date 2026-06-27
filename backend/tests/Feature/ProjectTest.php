<?php

namespace Tests\Feature;

use App\Enums\CatalogLevel;
use App\Enums\ProjectDeliverableStatus;
use App\Enums\Role;
use App\Models\CatalogItem;
use App\Models\Company;
use App\Models\Franchise;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class ProjectTest extends TestCase
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

    private function createRegularUser(): User
    {
        return User::factory()->create();
    }

    private function makeFranchise(array $attributes = []): Franchise
    {
        return Franchise::create(array_merge([
            'name' => 'Test Franchise',
            'type' => 'sm',
            'is_active' => true,
        ], $attributes));
    }

    private function makeCompany(Franchise $franchise, array $attributes = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Test Company',
            'sm_franchise_id' => $franchise->id,
        ], $attributes));
    }

    private function makeBundle(array $attributes = []): CatalogItem
    {
        return CatalogItem::create(array_merge([
            'level' => CatalogLevel::Bundle->value,
            'name_es' => 'Bundle Test',
            'name_en' => 'Bundle Test',
            'order_index' => 0,
            'is_monthly' => false,
        ], $attributes));
    }

    private function makeService(array $attributes = []): CatalogItem
    {
        return CatalogItem::create(array_merge([
            'level' => CatalogLevel::Service->value,
            'name_es' => 'Service Test',
            'name_en' => 'Service Test',
            'order_index' => 0,
            'is_monthly' => false,
        ], $attributes));
    }

    private function makeDeliverable(CatalogItem $service, array $attributes = []): CatalogItem
    {
        return CatalogItem::create(array_merge([
            'level' => CatalogLevel::Deliverable->value,
            'parent_id' => $service->id,
            'name_es' => 'Deliverable Test',
            'name_en' => 'Deliverable Test',
            'order_index' => 0,
            'is_monthly' => false,
            'estimated_hours' => 8.0,
        ], $attributes));
    }

    // ===========================================================================
    // POST /api/v1/projects — authentication & authorization
    // ===========================================================================

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->postJson('/api/v1/projects', []);

        $response->assertStatus(401);
    }

    public function test_regular_user_gets_403_on_create(): void
    {
        $user = $this->createRegularUser();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();

        $response = $this->actingAs($user)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $response->assertStatus(403);
    }

    // ===========================================================================
    // POST /api/v1/projects — happy path with a service
    // ===========================================================================

    public function test_superadmin_can_create_project_for_a_service(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService(['name_es' => 'Servicio A', 'name_en' => 'Service A']);
        $this->makeDeliverable($service, ['name_es' => 'Entregable 1', 'estimated_hours' => 8.0, 'order_index' => 0]);
        $this->makeDeliverable($service, ['name_es' => 'Entregable 2', 'estimated_hours' => 16.0, 'order_index' => 1]);

        $response = $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
            'notes' => 'First project',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.type', 'service');

        $this->assertDatabaseHas('projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'status' => 'active',
        ]);
    }

    public function test_project_creates_correct_number_of_deliverables_from_service(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        $this->makeDeliverable($service, ['name_es' => 'D1', 'estimated_hours' => 8.0, 'order_index' => 0]);
        $this->makeDeliverable($service, ['name_es' => 'D2', 'estimated_hours' => 8.0, 'order_index' => 1]);
        $this->makeDeliverable($service, ['name_es' => 'D3', 'estimated_hours' => 8.0, 'order_index' => 2]);

        $response = $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $response->assertStatus(201);

        $project = Project::first();
        $this->assertCount(3, $project->deliverables);
    }

    public function test_deliverable_dates_are_sequential_starting_from_start_date(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        // Each deliverable takes 1 business day (8h)
        $this->makeDeliverable($service, ['name_es' => 'D1', 'estimated_hours' => 8.0, 'order_index' => 0]);
        $this->makeDeliverable($service, ['name_es' => 'D2', 'estimated_hours' => 8.0, 'order_index' => 1]);

        // 2026-07-01 is a Wednesday
        $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $project = Project::first();
        $deliverables = $project->deliverables()->orderBy('order')->get();

        // D1 starts on 2026-07-01 (Wed), ends on 2026-07-01
        $this->assertEquals('2026-07-01', $deliverables[0]->estimated_start_date->toDateString());
        $this->assertEquals('2026-07-01', $deliverables[0]->estimated_end_date->toDateString());

        // D2 starts on 2026-07-02 (Thu), ends on 2026-07-02
        $this->assertEquals('2026-07-02', $deliverables[1]->estimated_start_date->toDateString());
        $this->assertEquals('2026-07-02', $deliverables[1]->estimated_end_date->toDateString());
    }

    public function test_weekend_dates_are_skipped_in_scheduling(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        // Friday + Monday scheduling test
        $this->makeDeliverable($service, ['name_es' => 'D1', 'estimated_hours' => 8.0, 'order_index' => 0]);
        $this->makeDeliverable($service, ['name_es' => 'D2', 'estimated_hours' => 8.0, 'order_index' => 1]);

        // 2026-07-03 is a Friday
        $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-03',
        ]);

        $project = Project::first();
        $deliverables = $project->deliverables()->orderBy('order')->get();

        // D1 is on Friday 2026-07-03
        $this->assertEquals('2026-07-03', $deliverables[0]->estimated_start_date->toDateString());

        // D2 skips Sat/Sun — lands on Monday 2026-07-06
        $this->assertEquals('2026-07-06', $deliverables[1]->estimated_start_date->toDateString());
    }

    // ===========================================================================
    // POST /api/v1/projects — bundle type
    // ===========================================================================

    public function test_project_with_bundle_creates_deliverables_from_all_services(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);

        $bundle = $this->makeBundle();
        $serviceA = $this->makeService(['name_es' => 'Service A', 'parent_id' => $bundle->id, 'order_index' => 0]);
        $serviceB = $this->makeService(['name_es' => 'Service B', 'parent_id' => $bundle->id, 'order_index' => 1]);
        $this->makeDeliverable($serviceA, ['name_es' => 'D-A1', 'estimated_hours' => 8.0, 'order_index' => 0]);
        $this->makeDeliverable($serviceA, ['name_es' => 'D-A2', 'estimated_hours' => 8.0, 'order_index' => 1]);
        $this->makeDeliverable($serviceB, ['name_es' => 'D-B1', 'estimated_hours' => 8.0, 'order_index' => 0]);

        $response = $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $bundle->id,
            'type' => 'bundle',
            'start_date' => '2026-07-01',
        ]);

        $response->assertStatus(201);

        $project = Project::first();
        // 2 from Service A + 1 from Service B = 3 total
        $this->assertCount(3, $project->deliverables);
    }

    // ===========================================================================
    // POST /api/v1/projects — single deliverable type
    // ===========================================================================

    public function test_project_with_single_deliverable_creates_one_row(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        $deliverable = $this->makeDeliverable($service, ['estimated_hours' => 4.0]);

        $response = $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $deliverable->id,
            'type' => 'deliverable',
            'start_date' => '2026-07-01',
        ]);

        $response->assertStatus(201);

        $project = Project::first();
        $this->assertCount(1, $project->deliverables);
    }

    // ===========================================================================
    // POST /api/v1/projects — admin_sm scope
    // ===========================================================================

    public function test_admin_sm_can_create_project_for_their_franchise(): void
    {
        $franchise = $this->makeFranchise();
        $adminSm = $this->createAdminSm($franchise);
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        $this->makeDeliverable($service, ['estimated_hours' => 8.0]);

        $response = $this->actingAs($adminSm)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $response->assertStatus(201);
    }

    public function test_admin_sm_cannot_create_project_for_another_franchise(): void
    {
        $franchise = $this->makeFranchise();
        $otherFranchise = $this->makeFranchise(['name' => 'Other Franchise']);
        $adminSm = $this->createAdminSm($franchise);
        $company = $this->makeCompany($otherFranchise);
        $service = $this->makeService();

        $response = $this->actingAs($adminSm)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $otherFranchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['franchise_id']);
    }

    // ===========================================================================
    // POST /api/v1/projects — validation
    // ===========================================================================

    public function test_store_returns_422_when_required_fields_are_missing(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/projects', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['company_id', 'franchise_id', 'catalog_item_id', 'type', 'start_date']);
    }

    public function test_store_returns_422_for_invalid_type(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'invalid_type',
            'start_date' => '2026-07-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_store_returns_422_for_nonexistent_company(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $service = $this->makeService();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => 999999,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['company_id']);
    }

    // ===========================================================================
    // GET /api/v1/projects — index
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_index(): void
    {
        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(401);
    }

    public function test_superadmin_can_list_all_projects(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        $this->makeDeliverable($service, ['estimated_hours' => 8.0]);

        $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/projects');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_sm_only_sees_projects_in_their_franchise(): void
    {
        $franchiseA = $this->makeFranchise(['name' => 'Franchise A']);
        $franchiseB = $this->makeFranchise(['name' => 'Franchise B']);
        $adminSm = $this->createAdminSm($franchiseA);

        $companyA = $this->makeCompany($franchiseA);
        $companyB = $this->makeCompany($franchiseB);
        $service = $this->makeService();
        $this->makeDeliverable($service, ['estimated_hours' => 8.0]);

        $superadmin = $this->createSuperadmin();

        // Project in franchise A
        $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $companyA->id,
            'franchise_id' => $franchiseA->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        // Project in franchise B (admin_sm should not see this)
        $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $companyB->id,
            'franchise_id' => $franchiseB->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $response = $this->actingAs($adminSm)->getJson('/api/v1/projects');

        $response->assertStatus(200);
        // admin_sm should only see the 1 project in their franchise
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($franchiseA->id, $response->json('data.0.franchise_id'));
    }

    // ===========================================================================
    // GET /api/v1/projects/{project} — show
    // ===========================================================================

    public function test_superadmin_can_show_a_project_with_deliverables(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        $this->makeDeliverable($service, ['estimated_hours' => 8.0, 'order_index' => 0]);
        $this->makeDeliverable($service, ['estimated_hours' => 8.0, 'order_index' => 1]);

        $createResponse = $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $projectId = $createResponse->json('data.id');

        $response = $this->actingAs($superadmin)->getJson("/api/v1/projects/{$projectId}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data.deliverables'));
    }

    public function test_show_returns_404_for_nonexistent_project(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/projects/999999');

        $response->assertStatus(404);
    }

    // ===========================================================================
    // GET /api/v1/projects — search & status filters (WILT-56)
    // ===========================================================================

    public function test_index_search_filter_matches_company_name(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $service = $this->makeService();
        $this->makeDeliverable($service, ['estimated_hours' => 8.0]);

        $companyMatch = $this->makeCompany($franchise, ['name' => 'Taco Express LLC']);
        $companyOther = $this->makeCompany($franchise, ['name' => 'Burger Palace Inc']);

        $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $companyMatch->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $companyOther->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/projects?search=Taco');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Taco Express LLC', $response->json('data.0.company_name'));
    }

    public function test_index_search_filter_is_case_insensitive(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $service = $this->makeService();
        $this->makeDeliverable($service, ['estimated_hours' => 8.0]);

        $company = $this->makeCompany($franchise, ['name' => 'Taco Express LLC']);

        $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/projects?search=taco');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_search_filter_returns_empty_when_no_match(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $service = $this->makeService();
        $this->makeDeliverable($service, ['estimated_hours' => 8.0]);

        $company = $this->makeCompany($franchise, ['name' => 'Taco Express LLC']);

        $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/projects?search=nonexistent');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_status_filter_returns_only_matching_projects(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        $this->makeDeliverable($service, ['estimated_hours' => 8.0]);

        // Create one active project via the API
        $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        // Create a paused project directly in the DB
        Project::create([
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
            'status' => 'paused',
        ]);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/projects?status=active');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('active', $response->json('data.0.status'));
    }

    public function test_index_invalid_status_filter_returns_all_projects(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        $this->makeDeliverable($service, ['estimated_hours' => 8.0]);

        $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        // Unknown status values are silently ignored — all projects are returned.
        $response = $this->actingAs($superadmin)->getJson('/api/v1/projects?status=bogus');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    // ===========================================================================
    // GET /api/v1/projects/{project} — WILT-58 KPI fields
    // ===========================================================================

    public function test_show_returns_correct_progress_percentage(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        $this->makeDeliverable($service, ['name_es' => 'D1', 'estimated_hours' => 8.0, 'order_index' => 0]);
        $this->makeDeliverable($service, ['name_es' => 'D2', 'estimated_hours' => 8.0, 'order_index' => 1]);
        $this->makeDeliverable($service, ['name_es' => 'D3', 'estimated_hours' => 8.0, 'order_index' => 2]);
        $this->makeDeliverable($service, ['name_es' => 'D4', 'estimated_hours' => 8.0, 'order_index' => 3]);

        $createResponse = $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $projectId = $createResponse->json('data.id');

        // Mark 2 of 4 deliverables as completed.
        $project = Project::find($projectId);
        $deliverables = $project->deliverables()->orderBy('order')->take(2)->get();
        foreach ($deliverables as $deliverable) {
            $deliverable->update(['status' => ProjectDeliverableStatus::Completed]);
        }

        $response = $this->actingAs($superadmin)->getJson("/api/v1/projects/{$projectId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.progress_percentage', 50);
        $response->assertJsonPath('data.deliverables_completed', 2);
        $response->assertJsonPath('data.deliverables_total', 4);
    }

    public function test_show_returns_estimated_end_date_as_max_deliverable_end_date(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        // Three deliverables with 8h each — sequential scheduling from 2026-07-01 (Wed)
        $this->makeDeliverable($service, ['name_es' => 'D1', 'estimated_hours' => 8.0, 'order_index' => 0]);
        $this->makeDeliverable($service, ['name_es' => 'D2', 'estimated_hours' => 8.0, 'order_index' => 1]);
        $this->makeDeliverable($service, ['name_es' => 'D3', 'estimated_hours' => 8.0, 'order_index' => 2]);

        $createResponse = $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $projectId = $createResponse->json('data.id');

        $response = $this->actingAs($superadmin)->getJson("/api/v1/projects/{$projectId}");

        $response->assertStatus(200);

        // The estimated_end_date must match the last deliverable's estimated_end_date.
        $project = Project::find($projectId);
        $expectedEndDate = $project->deliverables()->max('estimated_end_date');

        $this->assertEquals($expectedEndDate, $response->json('data.estimated_end_date'));
    }

    public function test_show_deliverables_are_groupable_by_phase(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService(['name_es' => 'Bundle Phase Test']);

        $servicePhaseA = $this->makeService(['name_es' => 'Phase A', 'parent_id' => null]);
        $servicePhaseB = $this->makeService(['name_es' => 'Phase B', 'parent_id' => null]);

        $bundle = CatalogItem::create([
            'level' => CatalogLevel::Bundle->value,
            'name_es' => 'Multi-phase Bundle',
            'name_en' => 'Multi-phase Bundle',
            'order_index' => 0,
            'is_monthly' => false,
        ]);

        // Re-parent services to the bundle.
        $servicePhaseA->update(['parent_id' => $bundle->id]);
        $servicePhaseB->update(['parent_id' => $bundle->id]);

        // 2 deliverables in Phase A, 1 in Phase B.
        $this->makeDeliverable($servicePhaseA, [
            'name_es' => 'A-D1', 'estimated_hours' => 8.0, 'order_index' => 0,
        ]);
        $this->makeDeliverable($servicePhaseA, [
            'name_es' => 'A-D2', 'estimated_hours' => 8.0, 'order_index' => 1,
        ]);
        $this->makeDeliverable($servicePhaseB, [
            'name_es' => 'B-D1', 'estimated_hours' => 8.0, 'order_index' => 0,
        ]);

        $createResponse = $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $bundle->id,
            'type' => 'bundle',
            'start_date' => '2026-07-01',
        ]);

        $projectId = $createResponse->json('data.id');

        $response = $this->actingAs($superadmin)->getJson("/api/v1/projects/{$projectId}");

        $response->assertStatus(200);

        $deliverables = collect($response->json('data.deliverables'));

        // All three deliverables must have a non-null phase for Gantt grouping.
        $this->assertCount(3, $deliverables);
        $phases = $deliverables->pluck('phase')->unique()->filter()->values();
        $this->assertGreaterThanOrEqual(1, $phases->count());
    }

    // ===========================================================================
    // PATCH /api/v1/projects/{project}/deliverables/{deliverable} — WILT-59
    // ===========================================================================

    /**
     * Helper: create a project with one deliverable and return both.
     *
     * @return array{project: Project, deliverable: ProjectDeliverable}
     */
    private function makeProjectWithDeliverable(User $user): array
    {
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        $this->makeDeliverable($service, ['estimated_hours' => 8.0, 'order_index' => 0]);
        $this->makeDeliverable($service, ['estimated_hours' => 8.0, 'order_index' => 1]);

        // Assign admin_sm to this franchise if needed.
        if ($user->hasRole(Role::ADMIN_SM)) {
            $user->update(['sm_franchise_id' => $franchise->id]);
        }

        $response = $this->actingAs($user)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $projectId = $response->json('data.id');
        $project = Project::find($projectId);
        $deliverable = $project->deliverables()->first();

        return ['project' => $project, 'deliverable' => $deliverable];
    }

    public function test_admin_sm_can_update_deliverable_status(): void
    {
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);
        $franchise = $this->makeFranchise();
        $adminSm = $this->createAdminSm($franchise);

        ['project' => $project, 'deliverable' => $deliverable] = $this->makeProjectWithDeliverable($adminSm);

        $response = $this->actingAs($adminSm)->patchJson(
            "/api/v1/projects/{$project->id}/deliverables/{$deliverable->id}",
            ['status' => 'in_progress']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.deliverable.status', 'in_progress');

        $this->assertDatabaseHas('project_deliverables', [
            'id' => $deliverable->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_deliverable_status_update_recalculates_project_progress(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = $this->makeFranchise();
        $company = $this->makeCompany($franchise);
        $service = $this->makeService();
        $this->makeDeliverable($service, ['estimated_hours' => 8.0, 'order_index' => 0]);
        $this->makeDeliverable($service, ['estimated_hours' => 8.0, 'order_index' => 1]);
        $this->makeDeliverable($service, ['estimated_hours' => 8.0, 'order_index' => 2]);
        $this->makeDeliverable($service, ['estimated_hours' => 8.0, 'order_index' => 3]);

        $createResponse = $this->actingAs($superadmin)->postJson('/api/v1/projects', [
            'company_id' => $company->id,
            'franchise_id' => $franchise->id,
            'catalog_item_id' => $service->id,
            'type' => 'service',
            'start_date' => '2026-07-01',
        ]);

        $projectId = $createResponse->json('data.id');
        $project = Project::find($projectId);
        $deliverable = $project->deliverables()->first();

        // Mark one of 4 deliverables as completed — expect 25%.
        $response = $this->actingAs($superadmin)->patchJson(
            "/api/v1/projects/{$projectId}/deliverables/{$deliverable->id}",
            ['status' => 'completed']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.progress_percentage', 25);
        $response->assertJsonPath('data.deliverables_completed', 1);
        $response->assertJsonPath('data.deliverables_total', 4);
    }

    public function test_sb_owner_cannot_update_deliverable_status(): void
    {
        SpatieRole::firstOrCreate(['name' => Role::SB_OWNER, 'guard_name' => 'web']);
        $superadmin = $this->createSuperadmin();

        ['project' => $project, 'deliverable' => $deliverable] = $this->makeProjectWithDeliverable($superadmin);

        $sbOwner = User::factory()->create();
        $sbOwner->assignRole(Role::SB_OWNER);

        $response = $this->actingAs($sbOwner)->patchJson(
            "/api/v1/projects/{$project->id}/deliverables/{$deliverable->id}",
            ['status' => 'completed']
        );

        $response->assertStatus(403);
    }

    public function test_update_deliverable_status_returns_422_for_invalid_status(): void
    {
        $superadmin = $this->createSuperadmin();

        ['project' => $project, 'deliverable' => $deliverable] = $this->makeProjectWithDeliverable($superadmin);

        $response = $this->actingAs($superadmin)->patchJson(
            "/api/v1/projects/{$project->id}/deliverables/{$deliverable->id}",
            ['status' => 'not_a_real_status']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_update_deliverable_returns_404_when_deliverable_belongs_to_different_project(): void
    {
        $superadmin = $this->createSuperadmin();

        ['project' => $project] = $this->makeProjectWithDeliverable($superadmin);

        // Create a second project and grab its first deliverable.
        ['project' => $otherProject, 'deliverable' => $otherDeliverable] = $this->makeProjectWithDeliverable($superadmin);

        // Pass the other project's deliverable to $project's URL — must 404.
        $response = $this->actingAs($superadmin)->patchJson(
            "/api/v1/projects/{$project->id}/deliverables/{$otherDeliverable->id}",
            ['status' => 'completed']
        );

        $response->assertStatus(404);
    }
}

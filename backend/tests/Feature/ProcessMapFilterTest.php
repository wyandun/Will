<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Models\ProcessMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

/**
 * Covers the GET /process-maps listing: franchise/client filters (the
 * "All franchises" / "All clients" dropdowns), combined filtering, the
 * unfiltered total, and pagination.
 */
class ProcessMapFilterTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    public function test_index_without_filters_returns_all_maps(): void
    {
        $superadmin = $this->createSuperadmin();
        ProcessMap::factory()->count(3)->create();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/process-maps');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_index_filters_by_franchise_only(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchiseA = Franchise::factory()->create();
        $franchiseB = Franchise::factory()->create();
        $companyA = $this->createCompany($franchiseA);
        $companyB = $this->createCompany($franchiseB);

        ProcessMap::factory()->count(2)->forCompany($companyA)->create();
        ProcessMap::factory()->forCompany($companyB)->create();

        $response = $this->actingAs($superadmin)
            ->getJson('/api/v1/process-maps?franchise_id='.$franchiseA->id);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $map) {
            $this->assertSame($companyA->id, $map['company_id']);
        }
    }

    public function test_index_filters_by_company_only(): void
    {
        $superadmin = $this->createSuperadmin();
        $companyA = $this->createCompany();
        $companyB = $this->createCompany();

        ProcessMap::factory()->count(2)->forCompany($companyA)->create();
        ProcessMap::factory()->forCompany($companyB)->create();

        $response = $this->actingAs($superadmin)
            ->getJson('/api/v1/process-maps?company_id='.$companyA->id);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_by_franchise_and_company_combined(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $companyA = $this->createCompany($franchise);
        $companyB = $this->createCompany($franchise);

        ProcessMap::factory()->forCompany($companyA)->create();
        ProcessMap::factory()->forCompany($companyB)->create();

        // Same franchise, but narrowed to a single client.
        $response = $this->actingAs($superadmin)->getJson(
            '/api/v1/process-maps?franchise_id='.$franchise->id.'&company_id='.$companyB->id
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($companyB->id, $response->json('data.0.company_id'));
    }

    public function test_index_pagination_respects_per_page(): void
    {
        $superadmin = $this->createSuperadmin();
        ProcessMap::factory()->count(3)->create();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/process-maps?per_page=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.per_page'));
    }

    public function test_admin_sm_can_list_and_filter_to_own_franchise(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);
        $company = $this->createCompany($franchise);

        ProcessMap::factory()->count(2)->forCompany($company)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/process-maps?franchise_id='.$franchise->id);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_includes_company_and_franchise_for_card_display(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        ProcessMap::factory()->forCompany($company)->create();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/process-maps');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.company.id', $company->id);
        $response->assertJsonPath('data.0.company.franchise.id', $franchise->id);
    }

    public function test_admin_sm_without_filter_sees_only_own_franchise_maps(): void
    {
        $ownFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($ownFranchise);

        $ownCompany = $this->createCompany($ownFranchise);
        $otherCompany = $this->createCompany($otherFranchise);
        ProcessMap::factory()->count(2)->forCompany($ownCompany)->create();
        ProcessMap::factory()->count(3)->forCompany($otherCompany)->create();

        // No franchise_id passed — the server must still scope to the admin's franchise.
        $response = $this->actingAs($admin)->getJson('/api/v1/process-maps');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $map) {
            $this->assertSame($ownCompany->id, $map['company_id']);
        }
    }

    public function test_admin_sm_cannot_list_other_franchise_via_filter(): void
    {
        $ownFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($ownFranchise);

        $ownCompany = $this->createCompany($ownFranchise);
        $otherCompany = $this->createCompany($otherFranchise);
        ProcessMap::factory()->forCompany($ownCompany)->create();
        ProcessMap::factory()->forCompany($otherCompany)->create();

        // Attempt to spoof another franchise — the forced scope overrides it,
        // so only the admin's own maps come back, never the other franchise's.
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/process-maps?franchise_id='.$otherFranchise->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        foreach ($response->json('data') as $map) {
            $this->assertSame($ownCompany->id, $map['company_id']);
        }
    }

    public function test_superadmin_still_sees_all_franchises(): void
    {
        $superadmin = $this->createSuperadmin();
        ProcessMap::factory()->forCompany($this->createCompany())->create();
        ProcessMap::factory()->forCompany($this->createCompany())->create();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/process-maps');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }
}

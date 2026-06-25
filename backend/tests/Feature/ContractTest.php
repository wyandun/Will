<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Services\DocuSealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithContracts;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use InteractsWithContracts;
    use RefreshDatabase;

    private FakeDocuSealService $fakeDocuseal;

    protected function setUp(): void
    {
        parent::setUp();

        // Never hit the network: bind a fake DocuSeal client.
        $this->fakeDocuseal = new FakeDocuSealService;
        $this->app->instance(DocuSealService::class, $this->fakeDocuseal);
    }

    // ---------------------------------------------------------------------
    // Create
    // ---------------------------------------------------------------------

    public function test_admin_sm_creates_contract_for_own_franchise_client(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $admin = $this->createAdminSm($franchise);

        $response = $this->actingAs($admin)->postJson('/api/v1/contracts', [
            'client_user_id' => $client->id,
            'title' => 'Franchise Agreement',
            'description' => 'Initial draft',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Franchise Agreement')
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.client_user_id', $client->id)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('contracts', [
            'company_id' => $company->id,
            'client_user_id' => $client->id,
            'status' => 'draft',
        ]);
    }

    public function test_admin_sm_cannot_create_for_other_franchise_client(): void
    {
        $ownFranchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $otherCompany = $this->createCompany($otherFranchise);
        $otherClient = $this->createClientUser($otherFranchise, $otherCompany);
        $admin = $this->createAdminSm($ownFranchise);

        $this->actingAs($admin)->postJson('/api/v1/contracts', [
            'client_user_id' => $otherClient->id,
            'title' => 'Should fail',
        ])->assertForbidden();
    }

    public function test_bb_employee_cannot_create(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $bb = $this->createBbEmployee($company);

        $this->actingAs($bb)->postJson('/api/v1/contracts', [
            'client_user_id' => $client->id,
            'title' => 'Nope',
        ])->assertForbidden();
    }

    public function test_readonly_admin_cannot_create(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $readonly = $this->createReadonlyAdmin();

        $this->actingAs($readonly)->postJson('/api/v1/contracts', [
            'client_user_id' => $client->id,
            'title' => 'Nope',
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Index scoping + filters
    // ---------------------------------------------------------------------

    public function test_admin_sm_only_sees_own_franchise_contracts(): void
    {
        $franchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();

        $company = $this->createCompany($franchise);
        $otherCompany = $this->createCompany($otherFranchise);

        $client = $this->createClientUser($franchise, $company);
        $otherClient = $this->createClientUser($otherFranchise, $otherCompany);

        $mine = $this->createContract($company, $client, ['title' => 'Mine']);
        $this->createContract($otherCompany, $otherClient, ['title' => 'Theirs']);

        $admin = $this->createAdminSm($franchise);

        $response = $this->actingAs($admin)->getJson('/api/v1/contracts');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertEquals([$mine->id], $ids);
    }

    public function test_bb_employee_only_sees_own_company_contracts(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $otherCompany = $this->createCompany($franchise);

        $client = $this->createClientUser($franchise, $company);
        $otherClient = $this->createClientUser($franchise, $otherCompany);

        $mine = $this->createContract($company, $client);
        $this->createContract($otherCompany, $otherClient);

        $bb = $this->createBbEmployee($company);

        $response = $this->actingAs($bb)->getJson('/api/v1/contracts');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertEquals([$mine->id], $ids);
    }

    public function test_index_filters_by_status_and_search(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $superadmin = $this->createSuperadmin();

        $draft = $this->createContract($company, $client, ['title' => 'Alpha Deal', 'status' => 'draft']);
        $sent = $this->createContract($company, $client, ['title' => 'Beta Deal', 'status' => 'sent']);

        $byStatus = $this->actingAs($superadmin)->getJson('/api/v1/contracts?status=sent');
        $byStatus->assertOk();
        $this->assertEquals([$sent->id], array_column($byStatus->json('data'), 'id'));

        $bySearch = $this->actingAs($superadmin)->getJson('/api/v1/contracts?search=Alpha');
        $bySearch->assertOk();
        $this->assertEquals([$draft->id], array_column($bySearch->json('data'), 'id'));
    }

    // ---------------------------------------------------------------------
    // Show
    // ---------------------------------------------------------------------

    public function test_show_includes_client_company_and_franchise(): void
    {
        $franchise = Franchise::factory()->create(['name' => 'SM Florida']);
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $contract = $this->createContract($company, $client);
        $admin = $this->createAdminSm($franchise);

        $this->actingAs($admin)->getJson("/api/v1/contracts/{$contract->id}")
            ->assertOk()
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.company.franchise.name', 'SM Florida');
    }

    public function test_admin_sm_cannot_view_other_franchise_contract(): void
    {
        $franchise = Franchise::factory()->create();
        $otherFranchise = Franchise::factory()->create();
        $otherCompany = $this->createCompany($otherFranchise);
        $otherClient = $this->createClientUser($otherFranchise, $otherCompany);
        $contract = $this->createContract($otherCompany, $otherClient);

        $admin = $this->createAdminSm($franchise);

        $this->actingAs($admin)->getJson("/api/v1/contracts/{$contract->id}")
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Update (status guard)
    // ---------------------------------------------------------------------

    public function test_admin_sm_can_update_draft(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $contract = $this->createContract($company, $client);
        $admin = $this->createAdminSm($franchise);

        $this->actingAs($admin)->putJson("/api/v1/contracts/{$contract->id}", [
            'title' => 'Renamed',
        ])->assertOk()->assertJsonPath('data.title', 'Renamed');
    }

    public function test_cannot_update_non_draft_contract(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $contract = $this->createContract($company, $client, ['status' => 'sent']);
        $admin = $this->createAdminSm($franchise);

        $this->actingAs($admin)->putJson("/api/v1/contracts/{$contract->id}", [
            'title' => 'Too late',
        ])->assertStatus(422);
    }

    public function test_bb_employee_cannot_update(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $contract = $this->createContract($company, $client);
        $bb = $this->createBbEmployee($company);

        $this->actingAs($bb)->putJson("/api/v1/contracts/{$contract->id}", [
            'title' => 'Nope',
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Delete
    // ---------------------------------------------------------------------

    public function test_admin_sm_can_delete_own_franchise_contract(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $contract = $this->createContract($company, $client);
        $admin = $this->createAdminSm($franchise);

        $this->actingAs($admin)->deleteJson("/api/v1/contracts/{$contract->id}")
            ->assertOk();

        $this->assertSoftDeleted('contracts', ['id' => $contract->id]);
    }

    public function test_bb_employee_cannot_delete(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $contract = $this->createContract($company, $client);
        $bb = $this->createBbEmployee($company);

        $this->actingAs($bb)->deleteJson("/api/v1/contracts/{$contract->id}")
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Send for signing
    // ---------------------------------------------------------------------

    public function test_send_sets_status_signers_and_sent_at(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $contract = $this->createContract($company, $client);
        $admin = $this->createAdminSm($franchise);

        $payload = [
            'template_id' => 7,
            'signers' => [
                ['name' => 'A', 'email' => 'a@example.com', 'role' => 'Elaborado por'],
                ['name' => 'B', 'email' => 'b@example.com', 'role' => 'Revisado por'],
                ['name' => 'C', 'email' => 'c@example.com', 'role' => 'Aprobado por'],
            ],
        ];

        $response = $this->actingAs($admin)->postJson("/api/v1/contracts/{$contract->id}/send", $payload);

        $response->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.docuseal_submission_id', 'fake-sub-123');

        $contract->refresh();
        $this->assertSame('sent', $contract->status);
        $this->assertNotNull($contract->sent_at);
        $this->assertCount(3, $contract->signers);
        $this->assertSame('pending', $contract->signers[0]['status']);
        $this->assertTrue($this->fakeDocuseal->createCalled);
    }

    public function test_cannot_send_non_draft_contract(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $contract = $this->createContract($company, $client, ['status' => 'sent']);
        $admin = $this->createAdminSm($franchise);

        $this->actingAs($admin)->postJson("/api/v1/contracts/{$contract->id}/send", [
            'template_id' => 7,
            'signers' => [
                ['name' => 'A', 'email' => 'a@example.com'],
                ['name' => 'B', 'email' => 'b@example.com'],
                ['name' => 'C', 'email' => 'c@example.com'],
            ],
        ])->assertStatus(422);
    }

    public function test_bb_employee_cannot_send(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $contract = $this->createContract($company, $client);
        $bb = $this->createBbEmployee($company);

        $this->actingAs($bb)->postJson("/api/v1/contracts/{$contract->id}/send", [
            'template_id' => 7,
            'signers' => [
                ['name' => 'A', 'email' => 'a@example.com'],
                ['name' => 'B', 'email' => 'b@example.com'],
                ['name' => 'C', 'email' => 'c@example.com'],
            ],
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Sync
    // ---------------------------------------------------------------------

    public function test_sync_marks_completed_submission_as_signed(): void
    {
        $franchise = Franchise::factory()->create();
        $company = $this->createCompany($franchise);
        $client = $this->createClientUser($franchise, $company);
        $contract = $this->createContract($company, $client, [
            'status' => 'sent',
            'docuseal_submission_id' => 'fake-sub-123',
        ]);
        $admin = $this->createAdminSm($franchise);

        $this->actingAs($admin)->postJson("/api/v1/contracts/{$contract->id}/sync")
            ->assertOk()
            ->assertJsonPath('data.status', 'signed')
            ->assertJsonPath('data.signed_document_url', 'https://docuseal.test/signed.pdf')
            ->assertJsonPath('data.certificate_url', 'https://docuseal.test/certificate.pdf');

        $contract->refresh();
        $this->assertSame('signed', $contract->status);
        $this->assertNotNull($contract->signed_at);
    }

    // ---------------------------------------------------------------------
    // Templates
    // ---------------------------------------------------------------------

    public function test_templates_endpoint_returns_docuseal_templates(): void
    {
        $franchise = Franchise::factory()->create();
        $admin = $this->createAdminSm($franchise);

        $this->actingAs($admin)->getJson('/api/v1/docuseal/templates')
            ->assertOk()
            ->assertJsonPath('data.0.id', 1);
    }
}

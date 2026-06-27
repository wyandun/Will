<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AssessmentContact;
use App\Models\Franchise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class AssessmentContactNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SpatieRole::firstOrCreate(['name' => Role::SUPERADMIN, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::ADMIN_SM, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::SB_OWNER, 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => Role::SB_EMPLOYEE, 'guard_name' => 'web']);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function createSuperadmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::SUPERADMIN);

        return $user;
    }

    private function createAdminSm(array $attributes = []): User
    {
        $franchise = Franchise::factory()->create();
        $user = User::factory()->create(array_merge(['sm_franchise_id' => $franchise->id], $attributes));
        $user->assignRole(Role::ADMIN_SM);

        return $user;
    }

    private function createSbOwner(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::SB_OWNER);

        return $user;
    }

    private function createSbEmployee(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::SB_EMPLOYEE);

        return $user;
    }

    private function createRegularUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function makeContact(array $attributes = []): AssessmentContact
    {
        return AssessmentContact::factory()->create($attributes);
    }

    // ===========================================================================
    // GET /api/v1/assessment-contacts  (index)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_index(): void
    {
        $response = $this->getJson('/api/v1/assessment-contacts');

        $response->assertStatus(401);
    }

    public function test_superadmin_can_list_assessment_contacts(): void
    {
        $superadmin = $this->createSuperadmin();
        $this->makeContact();
        $this->makeContact();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/assessment-contacts');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_sm_can_list_assessment_contacts(): void
    {
        $admin = $this->createAdminSm();
        $this->makeContact();

        $response = $this->actingAs($admin)->getJson('/api/v1/assessment-contacts');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_sb_owner_cannot_list_assessment_contacts(): void
    {
        $sbOwner = $this->createSbOwner();

        $response = $this->actingAs($sbOwner)->getJson('/api/v1/assessment-contacts');

        $response->assertStatus(403);
    }

    public function test_sb_employee_cannot_list_assessment_contacts(): void
    {
        $sbEmployee = $this->createSbEmployee();

        $response = $this->actingAs($sbEmployee)->getJson('/api/v1/assessment-contacts');

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_list_assessment_contacts(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/assessment-contacts');

        $response->assertStatus(403);
    }

    // ===========================================================================
    // GET /api/v1/assessment-contacts/{id}  (show)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_show(): void
    {
        $contact = $this->makeContact();

        $response = $this->getJson("/api/v1/assessment-contacts/{$contact->id}");

        $response->assertStatus(401);
    }

    public function test_superadmin_can_view_single_contact(): void
    {
        $superadmin = $this->createSuperadmin();
        $contact = $this->makeContact(['company_name' => 'Acme Corp']);

        $response = $this->actingAs($superadmin)->getJson("/api/v1/assessment-contacts/{$contact->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.company_name', 'Acme Corp');
    }

    public function test_admin_sm_can_view_single_contact(): void
    {
        $admin = $this->createAdminSm();
        $contact = $this->makeContact();

        $response = $this->actingAs($admin)->getJson("/api/v1/assessment-contacts/{$contact->id}");

        $response->assertStatus(200);
    }

    public function test_sb_owner_cannot_view_single_contact(): void
    {
        $sbOwner = $this->createSbOwner();
        $contact = $this->makeContact();

        $response = $this->actingAs($sbOwner)->getJson("/api/v1/assessment-contacts/{$contact->id}");

        $response->assertStatus(403);
    }

    // ===========================================================================
    // PATCH /api/v1/assessment-contacts/{id}/admin-note  (updateAdminNote)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_update_admin_note(): void
    {
        $contact = $this->makeContact();

        $response = $this->patchJson("/api/v1/assessment-contacts/{$contact->id}/admin-note", [
            'admin_note' => 'Some internal note.',
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_sm_can_save_admin_note(): void
    {
        $admin = $this->createAdminSm();
        $contact = $this->makeContact();

        $response = $this->actingAs($admin)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => 'This SB looks promising — schedule a follow-up call.']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Nota guardada correctamente.');
        $response->assertJsonPath('data.admin_note', 'This SB looks promising — schedule a follow-up call.');
    }

    public function test_admin_note_is_persisted_to_database(): void
    {
        $admin = $this->createAdminSm();
        $contact = $this->makeContact();
        $note = 'Requires additional documentation before approval.';

        $this->actingAs($admin)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => $note]
        );

        $this->assertDatabaseHas('assessment_contacts', [
            'id' => $contact->id,
            'admin_note' => $note,
        ]);
    }

    public function test_admin_noted_by_user_id_is_stamped_on_save(): void
    {
        $admin = $this->createAdminSm();
        $contact = $this->makeContact();

        $this->actingAs($admin)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => 'Approved with conditions.']
        );

        $this->assertDatabaseHas('assessment_contacts', [
            'id' => $contact->id,
            'admin_noted_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_noted_at_is_stamped_on_save(): void
    {
        $admin = $this->createAdminSm();
        $contact = $this->makeContact();

        $this->actingAs($admin)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => 'On track.']
        );

        $contact->refresh();
        $this->assertNotNull($contact->admin_noted_at);
    }

    public function test_superadmin_can_save_admin_note(): void
    {
        $superadmin = $this->createSuperadmin();
        $contact = $this->makeContact();

        $response = $this->actingAs($superadmin)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => 'Superadmin override note.']
        );

        $response->assertStatus(200);
    }

    public function test_admin_note_can_be_cleared_with_null(): void
    {
        $admin = $this->createAdminSm();
        $contact = $this->makeContact(['admin_note' => 'Old note']);

        $response = $this->actingAs($admin)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => null]
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('assessment_contacts', [
            'id' => $contact->id,
            'admin_note' => null,
        ]);
    }

    public function test_admin_note_can_be_sent_empty_string(): void
    {
        $admin = $this->createAdminSm();
        $contact = $this->makeContact();

        $response = $this->actingAs($admin)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            []
        );

        // Missing field is treated as nullable — should still succeed
        $response->assertStatus(200);
    }

    public function test_admin_note_rejects_string_over_2000_chars(): void
    {
        $admin = $this->createAdminSm();
        $contact = $this->makeContact();
        $tooLong = str_repeat('a', 2001);

        $response = $this->actingAs($admin)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => $tooLong]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['admin_note']);
    }

    public function test_admin_note_accepts_exactly_2000_chars(): void
    {
        $admin = $this->createAdminSm();
        $contact = $this->makeContact();
        $exactly2000 = str_repeat('a', 2000);

        $response = $this->actingAs($admin)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => $exactly2000]
        );

        $response->assertStatus(200);
    }

    public function test_sb_owner_cannot_save_admin_note(): void
    {
        $sbOwner = $this->createSbOwner();
        $contact = $this->makeContact();

        $response = $this->actingAs($sbOwner)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => 'Unauthorized note.']
        );

        $response->assertStatus(403);
    }

    public function test_sb_employee_cannot_save_admin_note(): void
    {
        $sbEmployee = $this->createSbEmployee();
        $contact = $this->makeContact();

        $response = $this->actingAs($sbEmployee)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => 'Unauthorized note.']
        );

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_save_admin_note(): void
    {
        $user = $this->createRegularUser();
        $contact = $this->makeContact();

        $response = $this->actingAs($user)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => 'Sneaky note.']
        );

        $response->assertStatus(403);
    }

    public function test_updating_note_does_not_overwrite_existing_contact_notes_field(): void
    {
        $admin = $this->createAdminSm();
        $contact = $this->makeContact(['notes' => 'Original public notes from the contact.']);

        $this->actingAs($admin)->patchJson(
            "/api/v1/assessment-contacts/{$contact->id}/admin-note",
            ['admin_note' => 'Admin internal note.']
        );

        $this->assertDatabaseHas('assessment_contacts', [
            'id' => $contact->id,
            'notes' => 'Original public notes from the contact.',
            'admin_note' => 'Admin internal note.',
        ]);
    }

    public function test_response_includes_admin_note_in_data(): void
    {
        $admin = $this->createAdminSm();
        $contact = $this->makeContact();

        $response = $this->actingAs($admin)->getJson("/api/v1/assessment-contacts/{$contact->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'status',
                'company_name',
                'notes',
                'admin_note',
                'admin_noted_at',
            ],
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Franchise;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Feature tests for the user invitation flow.
 *
 * Covers all acceptance criteria for WILT-35:
 *  - admin_sm / superadmin can send an invitation with an activation link.
 *  - The invited user activates their account and sets their own password.
 *  - Auto-login: a Sanctum token is returned upon acceptance.
 *
 * Endpoints under test:
 *  Protected (auth:sanctum + inviteUsers policy):
 *    GET    /api/v1/invitations                  – index
 *    POST   /api/v1/invitations                  – store
 *    POST   /api/v1/invitations/{user}/resend    – resend
 *    DELETE /api/v1/invitations/{user}           – destroy
 *
 *  Public (no auth required):
 *    GET    /api/v1/invitations/{token}/verify   – verify
 *    POST   /api/v1/invitations/{token}/accept   – accept
 */
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Bootstrap
    // ---------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure all relevant Spatie roles exist before each test.
        $roles = [
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::SYSTEM_ADMIN_READONLY,
            Role::ADMIN_SM,
            Role::SB_OWNER,
            Role::SB_EMPLOYEE,
            Role::BB_EMPLOYEE,
            Role::SUB_FRANCHISE_OWNER,
            Role::SUB_FRANCHISE_ADMIN,
        ];

        foreach ($roles as $role) {
            SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Reset rate limiter cache so throttle:invitation doesn't accumulate across tests.
        Cache::flush();

        // Fake HIBP API calls so ->uncompromised() always passes in tests.
        // This prevents network dependency and lets us use any well-formatted
        // test password without risking a real breach-database hit.
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
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
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::ADMIN_SM);

        return $user;
    }

    private function createSystemAdmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::SYSTEM_ADMIN);

        return $user;
    }

    private function createRegularUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Create a User record that represents a pending (not yet accepted) invitation.
     */
    private function createPendingInvitation(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'invitation_token' => Str::random(64),
            'invitation_expires_at' => now()->addDays(7),
            'invitation_accepted_at' => null,
            'email_verified_at' => null,
        ], $attributes));
    }

    private function createFranchise(array $attributes = []): Franchise
    {
        return Franchise::factory()->create($attributes);
    }

    /** Default valid payload for sending an invitation. */
    private function validInvitePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'role' => Role::SB_OWNER,
            'company_name' => 'Acme LLC',
        ], $overrides);
    }

    // ===========================================================================
    // GET /api/v1/invitations  (index)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_invitation_index(): void
    {
        $response = $this->getJson('/api/v1/invitations');

        $response->assertStatus(401);
    }

    public function test_superadmin_can_list_pending_invitations(): void
    {
        $superadmin = $this->createSuperadmin();
        $this->createPendingInvitation(['email' => 'invite1@test.com']);
        $this->createPendingInvitation(['email' => 'invite2@test.com']);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_sm_can_list_pending_invitations(): void
    {
        $franchise = $this->createFranchise();
        $adminSm = $this->createAdminSm(['sm_franchise_id' => $franchise->id]);
        $this->createPendingInvitation(['email' => 'pending@test.com', 'sm_franchise_id' => $franchise->id]);

        $response = $this->actingAs($adminSm)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_invitation_index_returns_correct_json_structure(): void
    {
        $superadmin = $this->createSuperadmin();
        $this->createPendingInvitation(['email' => 'struct@test.com']);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'invitation_expires_at',
                    'role',
                ],
            ],
            'meta' => [
                'current_page',
                'total',
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
        ]);
    }

    public function test_invitation_index_does_not_trigger_n_plus_1_for_roles(): void
    {
        $superadmin = $this->createSuperadmin();

        // Create a batch of pending invitations, each with a role assigned.
        foreach (range(1, 5) as $i) {
            $pending = $this->createPendingInvitation(['email' => "n1user{$i}@test.com"]);
            $pending->assignRole(Role::SB_OWNER);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/invitations');
        $response->assertStatus(200)->assertJsonCount(5, 'data');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Eager-loading collapses roles + invitedBy into 2 additional queries for
        // the entire collection, not 1 per user.  With auth overhead the total
        // count is small and constant regardless of collection size.
        // Pagination adds 1 extra COUNT(*) query.
        // Upper-bound chosen generously; failure here means a relation was
        // accidentally un-eager-loaded (e.g. Spatie role->permissions queried per user).
        $this->assertCount(5, $response->json('data'));
        $this->assertLessThanOrEqual(13, count($queries),
            'Query count scaled with collection size — possible N+1. Queries: '
            .implode("\n", array_column($queries, 'query'))
        );
    }

    public function test_invitation_index_does_not_include_accepted_invitations(): void
    {
        $superadmin = $this->createSuperadmin();
        // Pending
        $this->createPendingInvitation(['email' => 'pending@test.com']);
        // Accepted (invitation_token cleared)
        User::factory()->create([
            'email' => 'accepted@test.com',
            'invitation_token' => null,
            'invitation_accepted_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_regular_user_is_forbidden_from_invitation_index(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/invitations');

        $response->assertStatus(403);
    }

    public function test_system_admin_can_access_invitation_index(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
    }

    public function test_index_returns_403_if_user_has_null_franchise(): void
    {
        // A non-superadmin with sm_franchise_id = null would produce
        // WHERE sm_franchise_id IS NULL, potentially leaking cross-tenant rows.
        $adminSm = $this->createAdminSm(['sm_franchise_id' => null]);

        $response = $this->actingAs($adminSm)->getJson('/api/v1/invitations');

        $response->assertStatus(403);
    }

    // ===========================================================================
    // POST /api/v1/invitations  (store)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_invitation_store(): void
    {
        $response = $this->postJson('/api/v1/invitations', $this->validInvitePayload());

        $response->assertStatus(401);
    }

    public function test_superadmin_can_send_an_invitation(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'invitation.sent_success');
    }

    public function test_admin_sm_can_send_an_invitation(): void
    {
        Notification::fake();
        $adminSm = $this->createAdminSm();

        $response = $this->actingAs($adminSm)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'from_admin_sm@test.com',
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }

    public function test_sending_invitation_creates_user_in_database(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'name' => 'Carlos López',
                'email' => 'carlos@test.com',
            ]));

        $this->assertDatabaseHas('users', [
            'name' => 'Carlos López',
            'email' => 'carlos@test.com',
        ]);
    }

    public function test_invited_user_has_invitation_token_set(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'tokencheck@test.com',
            ]));

        $user = User::where('email', 'tokencheck@test.com')->first();
        $this->assertNotNull($user->invitation_token);
        $this->assertEquals(64, strlen($user->invitation_token));
    }

    public function test_invited_user_has_expiry_set_to_seven_days(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'expiry@test.com',
            ]));

        $user = User::where('email', 'expiry@test.com')->first();
        $this->assertNotNull($user->invitation_expires_at);
        // Should be approximately 7 days from now (within 1 minute tolerance)
        $this->assertTrue($user->invitation_expires_at->isFuture());
        $diff = now()->diffInDays($user->invitation_expires_at, false);
        $this->assertEqualsWithDelta(7, $diff, 0.1);
    }

    public function test_invited_user_has_inviter_id_set_to_inviter(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'invitedby@test.com',
            ]));

        $user = User::where('email', 'invitedby@test.com')->first();
        $this->assertEquals($superadmin->id, $user->inviter_id);
    }

    public function test_invited_user_has_correct_role_assigned(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'rolecheck@test.com',
                'role' => Role::SB_EMPLOYEE,
            ]));

        $user = User::where('email', 'rolecheck@test.com')->first();
        $this->assertTrue($user->hasRole(Role::SB_EMPLOYEE));
    }

    public function test_invitation_notification_is_dispatched(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'notify@test.com',
            ]));

        $invitedUser = User::where('email', 'notify@test.com')->first();
        Notification::assertSentTo($invitedUser, UserInvitationNotification::class);
    }

    public function test_invitation_notification_is_sent_exactly_once(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'onceonly@test.com',
            ]));

        Notification::assertCount(1);
    }

    public function test_store_returns_activation_url_in_non_production(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'devurl@test.com',
            ]));

        // In the test environment (non-production), activation_url should be present.
        $response->assertStatus(201);
        $activationUrl = $response->json('data.activation_url');
        $this->assertNotNull($activationUrl);
        $this->assertStringContainsString('/invite/', $activationUrl);
    }

    // --- Re-invitation of same pending email --------------------------------

    public function test_re_inviting_a_pending_email_returns_422(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        $this->createPendingInvitation([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'invitation_token' => 'old_token_abc123',
        ])->assignRole(Role::SB_OWNER);

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_re_inviting_pending_email_does_not_send_notification(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        $existing = $this->createPendingInvitation([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
        ]);
        $existing->assignRole(Role::SB_OWNER);

        $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload());

        Notification::assertNotSentTo($existing, UserInvitationNotification::class);
    }

    // --- Rejected scenarios -------------------------------------------------

    public function test_inviting_already_accepted_email_returns_422(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        // User with accepted invitation
        User::factory()->create([
            'email' => 'active@test.com',
            'invitation_accepted_at' => now()->subDay(),
            'invitation_token' => null,
        ]);

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'active@test.com',
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_inviting_revoked_placeholder_restores_and_re_invites(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        // Revoked invitation placeholder: soft-deleted, never accepted
        $revoked = $this->createPendingInvitation(['email' => 'revoked@test.com']);
        $revoked->delete();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'revoked@test.com',
            ]));

        $response->assertStatus(201);
        Notification::assertSentTo($revoked->fresh(), UserInvitationNotification::class);
    }

    public function test_inviting_soft_deleted_accepted_user_returns_422(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();

        // Soft-deleted user who previously accepted their invitation (real account)
        $deleted = User::factory()->create([
            'email' => 'deleted@test.com',
            'invitation_accepted_at' => now()->subMonth(),
            'invitation_token' => null,
        ]);
        $deleted->delete();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'deleted@test.com',
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    // --- Validation: store --------------------------------------------------

    public function test_store_invitation_validates_required_name(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload(['name' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_invitation_validates_required_email(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload(['email' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_store_invitation_validates_email_format(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'email' => 'not-a-valid-email',
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_store_invitation_validates_required_role(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload(['role' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    public function test_store_invitation_rejects_superadmin_role(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'role' => Role::SUPERADMIN,
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    public function test_store_invitation_rejects_unknown_role(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload([
                'role' => 'hacker_role',
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    public function test_store_invitation_accepts_all_valid_non_superadmin_roles(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();
        $franchise = $this->createFranchise();

        $validRoles = [
            Role::SYSTEM_ADMIN,
            Role::SYSTEM_ADMIN_READONLY,
            Role::ADMIN_SM,
            Role::SB_OWNER,
            Role::SB_EMPLOYEE,
            Role::BB_EMPLOYEE,
            Role::SUB_FRANCHISE_OWNER,
            Role::SUB_FRANCHISE_ADMIN,
        ];

        foreach ($validRoles as $i => $role) {
            // admin_sm invitations require an explicit sm_franchise_id in the payload.
            $extra = $role === Role::ADMIN_SM
                ? ['sm_franchise_id' => $franchise->id]
                : [];

            // bb_employee invitations require a sb_owner_id whose company_id is set,
            // so we seed a SB Owner+Company in the test franchise for the link.
            if ($role === Role::BB_EMPLOYEE) {
                $company = Company::create([
                    'name' => 'Test LLC for investor invite',
                    'sm_franchise_id' => $franchise->id,
                ]);
                $owner = User::factory()->create([
                    'sm_franchise_id' => $franchise->id,
                    'company_id' => $company->id,
                    'invitation_accepted_at' => now(),
                ]);
                SpatieRole::firstOrCreate(['name' => Role::SB_OWNER, 'guard_name' => 'web']);
                $owner->assignRole(Role::SB_OWNER);
                $extra = ['sm_franchise_id' => $franchise->id, 'sb_owner_id' => $owner->id];
            }

            $response = $this->actingAs($superadmin)
                ->postJson('/api/v1/invitations', $this->validInvitePayload(array_merge([
                    'email' => "role_test_{$i}@test.com",
                    'role' => $role,
                ], $extra)));

            $this->assertSame(201, $response->status(), "Role {$role} should be accepted.");
        }
    }

    // --- Authorization: store -----------------------------------------------

    public function test_regular_user_cannot_send_invitations(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/invitations', $this->validInvitePayload());

        $response->assertStatus(403);
    }

    public function test_system_admin_can_send_invitations(): void
    {
        Notification::fake();
        $admin = $this->createSystemAdmin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/invitations', $this->validInvitePayload());

        $response->assertStatus(201);
    }

    // ===========================================================================
    // POST /api/v1/invitations/{user}/resend  (resend)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_resend(): void
    {
        $pending = $this->createPendingInvitation();

        $response = $this->postJson("/api/v1/invitations/{$pending->id}/resend");

        $response->assertStatus(401);
    }

    public function test_superadmin_can_resend_invitation(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();
        $pending = $this->createPendingInvitation(['email' => 'resend@test.com']);

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/invitations/{$pending->id}/resend");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'invitation.resent_success');
    }

    public function test_admin_sm_can_resend_invitation(): void
    {
        Notification::fake();
        $franchise = $this->createFranchise();
        $adminSm = $this->createAdminSm(['sm_franchise_id' => $franchise->id]);
        $pending = $this->createPendingInvitation(['email' => 'resend_by_sm@test.com', 'sm_franchise_id' => $franchise->id]);

        $response = $this->actingAs($adminSm)
            ->postJson("/api/v1/invitations/{$pending->id}/resend");

        $response->assertStatus(200);
    }

    public function test_resend_generates_a_new_token(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();
        $originalToken = 'fixed_original_token_0000000000000000000000000000000000000000000000000000';
        $pending = $this->createPendingInvitation([
            'email' => 'newtoken@test.com',
            'invitation_token' => $originalToken,
        ]);

        $this->actingAs($superadmin)
            ->postJson("/api/v1/invitations/{$pending->id}/resend");

        $pending->refresh();
        $this->assertNotEquals($originalToken, $pending->invitation_token);
        $this->assertNotNull($pending->invitation_token);
    }

    public function test_resend_extends_the_expiry_to_seven_days(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();
        $pending = $this->createPendingInvitation([
            'email' => 'extendexpiry@test.com',
            'invitation_expires_at' => now()->addDay(), // almost expired
        ]);

        $this->actingAs($superadmin)
            ->postJson("/api/v1/invitations/{$pending->id}/resend");

        $pending->refresh();
        $diff = now()->diffInDays($pending->invitation_expires_at, false);
        $this->assertEqualsWithDelta(7, $diff, 0.1);
    }

    public function test_resend_sends_a_new_notification(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();
        $pending = $this->createPendingInvitation(['email' => 'notifyresend@test.com']);

        $this->actingAs($superadmin)
            ->postJson("/api/v1/invitations/{$pending->id}/resend");

        Notification::assertSentTo($pending, UserInvitationNotification::class);
    }

    public function test_resend_returns_422_if_invitation_already_accepted(): void
    {
        $superadmin = $this->createSuperadmin();
        $accepted = User::factory()->create([
            'email' => 'accepted@test.com',
            'invitation_accepted_at' => now()->subDay(),
            'invitation_token' => null,
        ]);

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/invitations/{$accepted->id}/resend");

        $response->assertStatus(422);
    }

    public function test_resend_returns_422_if_user_has_no_invitation_token(): void
    {
        // Edge case: invitation_accepted_at is null but invitation_token is also null
        // (e.g. a user that was revoked then un-soft-deleted manually).
        $superadmin = $this->createSuperadmin();
        $user = User::factory()->create([
            'email' => 'no_token@test.com',
            'invitation_token' => null,
            'invitation_accepted_at' => null,
        ]);

        $response = $this->actingAs($superadmin)
            ->postJson("/api/v1/invitations/{$user->id}/resend");

        $response->assertStatus(422);
    }

    public function test_regular_user_cannot_resend_invitation(): void
    {
        $user = $this->createRegularUser();
        $pending = $this->createPendingInvitation(['email' => 'target_resend@test.com']);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/invitations/{$pending->id}/resend");

        $response->assertStatus(403);
    }

    // ===========================================================================
    // DELETE /api/v1/invitations/{user}  (destroy / revoke)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_revoke(): void
    {
        $pending = $this->createPendingInvitation();

        $response = $this->deleteJson("/api/v1/invitations/{$pending->id}");

        $response->assertStatus(401);
    }

    public function test_superadmin_can_revoke_invitation(): void
    {
        $superadmin = $this->createSuperadmin();
        $pending = $this->createPendingInvitation(['email' => 'revoke@test.com']);

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/invitations/{$pending->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'invitation.revoked_success');
    }

    public function test_admin_sm_can_revoke_invitation(): void
    {
        $franchise = $this->createFranchise();
        $adminSm = $this->createAdminSm(['sm_franchise_id' => $franchise->id]);
        $pending = $this->createPendingInvitation(['email' => 'revoke_by_sm@test.com', 'sm_franchise_id' => $franchise->id]);

        $response = $this->actingAs($adminSm)
            ->deleteJson("/api/v1/invitations/{$pending->id}");

        $response->assertStatus(200);
    }

    public function test_revoke_soft_deletes_the_placeholder_user(): void
    {
        $superadmin = $this->createSuperadmin();
        $pending = $this->createPendingInvitation(['email' => 'softdelete@test.com']);
        $pendingId = $pending->id;

        $this->actingAs($superadmin)
            ->deleteJson("/api/v1/invitations/{$pendingId}");

        // Should be soft-deleted (trashed), not hard-deleted
        $this->assertSoftDeleted('users', ['id' => $pendingId]);
    }

    public function test_revoked_invitation_no_longer_appears_in_index(): void
    {
        $superadmin = $this->createSuperadmin();
        $pending = $this->createPendingInvitation(['email' => 'disappeared@test.com']);

        $this->actingAs($superadmin)
            ->deleteJson("/api/v1/invitations/{$pending->id}");

        $response = $this->actingAs($superadmin)->getJson('/api/v1/invitations');
        $response->assertJsonCount(0, 'data');
    }

    public function test_revoke_returns_422_if_invitation_already_accepted(): void
    {
        $superadmin = $this->createSuperadmin();
        $accepted = User::factory()->create([
            'email' => 'cant_revoke@test.com',
            'invitation_accepted_at' => now()->subDay(),
            'invitation_token' => null,
        ]);

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/invitations/{$accepted->id}");

        $response->assertStatus(422);
    }

    public function test_revoke_returns_422_if_user_has_no_invitation_token(): void
    {
        // Edge case: invitation_accepted_at is null but invitation_token is also null.
        $superadmin = $this->createSuperadmin();
        $user = User::factory()->create([
            'email' => 'no_token_revoke@test.com',
            'invitation_token' => null,
            'invitation_accepted_at' => null,
        ]);

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/invitations/{$user->id}");

        $response->assertStatus(422);
    }

    public function test_regular_user_cannot_revoke_invitation(): void
    {
        $user = $this->createRegularUser();
        $pending = $this->createPendingInvitation(['email' => 'safe_revoke@test.com']);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/invitations/{$pending->id}");

        $response->assertStatus(403);
    }

    public function test_revoke_nulls_invitation_token(): void
    {
        $superadmin = $this->createSuperadmin();
        $pending = $this->createPendingInvitation([
            'email' => 'token_null_check@test.com',
            'invitation_token' => 'token_to_be_nulled',
        ]);
        $pendingId = $pending->id;

        $this->actingAs($superadmin)
            ->deleteJson("/api/v1/invitations/{$pendingId}");

        // Verify the soft-deleted record has token nullified (defense-in-depth)
        $softDeletedUser = User::withTrashed()->findOrFail($pendingId);
        $this->assertNull($softDeletedUser->invitation_token);
        $this->assertNull($softDeletedUser->invitation_expires_at);
    }

    // ===========================================================================
    // GET /api/v1/invitations/{token}/verify  (public — no auth)
    // ===========================================================================

    public function test_verify_returns_200_and_user_info_for_valid_token(): void
    {
        $pending = $this->createPendingInvitation([
            'name' => 'María Pérez',
            'email' => 'maria@test.com',
            'invitation_token' => 'valid_token_abc',
        ]);
        $pending->assignRole(Role::SB_OWNER);

        $response = $this->getJson('/api/v1/invitations/valid_token_abc/verify');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.email', 'm***@test.com');
        $response->assertJsonMissingPath('data.role');
    }

    public function test_verify_returns_correct_json_structure(): void
    {
        $pending = $this->createPendingInvitation(['invitation_token' => 'struct_token_xyz']);

        $response = $this->getJson('/api/v1/invitations/struct_token_xyz/verify');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['email'],
        ]);
        $response->assertJsonMissingPath('data.role');
    }

    public function test_verify_returns_404_for_nonexistent_token(): void
    {
        $response = $this->getJson('/api/v1/invitations/nonexistent_token_999/verify');

        $response->assertStatus(404);
    }

    public function test_verify_returns_404_for_already_accepted_token(): void
    {
        // When a user accepts, the token is cleared → any old token is invalid
        $accepted = User::factory()->create([
            'email' => 'accepted_verify@test.com',
            'invitation_token' => null,
            'invitation_accepted_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/invitations/some_old_cleared_token/verify');

        $response->assertStatus(404);
    }

    public function test_verify_returns_404_for_expired_token(): void
    {
        $pending = $this->createPendingInvitation([
            'invitation_token' => 'expired_token_abc',
            'invitation_expires_at' => now()->subDay(), // already expired
        ]);

        $response = $this->getJson('/api/v1/invitations/expired_token_abc/verify');

        $response->assertStatus(404);
    }

    // ===========================================================================
    // POST /api/v1/invitations/{token}/accept  (public — no auth)
    // ===========================================================================

    public function test_accept_returns_200_with_auth_payload_for_valid_token_and_password(): void
    {
        $pending = $this->createPendingInvitation([
            'name' => 'Luisa Ramírez',
            'email' => 'luisa@test.com',
            'invitation_token' => 'accept_token_ok',
        ]);
        $pending->assignRole(Role::SB_OWNER);

        $response = $this->postJson('/api/v1/invitations/accept_token_ok/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'invitation.accepted_success');
    }

    public function test_accept_response_contains_auth_structure(): void
    {
        $pending = $this->createPendingInvitation(['invitation_token' => 'auth_struct_token']);
        $pending->assignRole(Role::SB_OWNER);

        $response = $this->postJson('/api/v1/invitations/auth_struct_token/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'avatar_path',
                    'sm_franchise_id',
                ],
                'token',
                'role',
                'permissions',
            ],
        ]);
    }

    public function test_accept_clears_invitation_token(): void
    {
        $pending = $this->createPendingInvitation([
            'email' => 'cleartoken@test.com',
            'invitation_token' => 'clear_me_token',
        ]);
        $pending->assignRole(Role::SB_OWNER);

        $this->postJson('/api/v1/invitations/clear_me_token/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ]);

        $pending->refresh();
        $this->assertNull($pending->invitation_token);
    }

    public function test_accept_sets_invitation_accepted_at(): void
    {
        $pending = $this->createPendingInvitation([
            'email' => 'setat@test.com',
            'invitation_token' => 'setat_token_abc',
        ]);
        $pending->assignRole(Role::SB_OWNER);

        $this->postJson('/api/v1/invitations/setat_token_abc/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ]);

        $pending->refresh();
        $this->assertNotNull($pending->invitation_accepted_at);
    }

    public function test_accept_sets_email_verified_at(): void
    {
        $pending = $this->createPendingInvitation([
            'email' => 'emailverify@test.com',
            'invitation_token' => 'emailverify_token',
            'email_verified_at' => null,
        ]);
        $pending->assignRole(Role::SB_OWNER);

        $this->postJson('/api/v1/invitations/emailverify_token/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ]);

        $pending->refresh();
        $this->assertNotNull($pending->email_verified_at);
    }

    public function test_accept_hashes_the_password(): void
    {
        $pending = $this->createPendingInvitation([
            'email' => 'hashpw@test.com',
            'invitation_token' => 'hashpw_token_123',
        ]);
        $pending->assignRole(Role::SB_OWNER);

        $this->postJson('/api/v1/invitations/hashpw_token_123/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ]);

        $pending->refresh();
        $this->assertNotEquals('MySecure123!', $pending->password);
        $this->assertTrue(Hash::check('MySecure123!', $pending->password));
    }

    public function test_accept_returns_a_sanctum_token(): void
    {
        $pending = $this->createPendingInvitation(['invitation_token' => 'sanctum_token_abc']);
        $pending->assignRole(Role::SB_OWNER);

        $response = $this->postJson('/api/v1/invitations/sanctum_token_abc/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ]);

        $token = $response->json('data.token');
        $this->assertNotNull($token);
        $this->assertIsString($token);
        $this->assertStringContainsString('|', $token); // Sanctum format: id|plainToken
    }

    public function test_accept_returns_correct_role_name(): void
    {
        $pending = $this->createPendingInvitation(['invitation_token' => 'role_return_token']);
        $pending->assignRole(Role::ADMIN_SM);

        $response = $this->postJson('/api/v1/invitations/role_return_token/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ]);

        $response->assertJsonPath('data.role', Role::ADMIN_SM);
    }

    // --- accept: validation --------------------------------------------------

    public function test_accept_validates_required_password(): void
    {
        $pending = $this->createPendingInvitation(['invitation_token' => 'val_pw_required']);

        $response = $this->postJson('/api/v1/invitations/val_pw_required/accept', [
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_accept_validates_password_minimum_8_characters(): void
    {
        $pending = $this->createPendingInvitation(['invitation_token' => 'val_pw_min']);

        $response = $this->postJson('/api/v1/invitations/val_pw_min/accept', [
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_accept_validates_password_confirmation_must_match(): void
    {
        $pending = $this->createPendingInvitation(['invitation_token' => 'val_pw_confirm']);

        $response = $this->postJson('/api/v1/invitations/val_pw_confirm/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'DifferentPass!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_accept_accepts_password_exactly_8_characters(): void
    {
        $pending = $this->createPendingInvitation(['invitation_token' => 'val_pw_exact8']);
        $pending->assignRole(Role::SB_OWNER);

        // Password must be 8+ chars, mixed case, and contain numbers (Password rule).
        $response = $this->postJson('/api/v1/invitations/val_pw_exact8/accept', [
            'password' => 'Abc1defg',
            'password_confirmation' => 'Abc1defg',
        ]);

        $response->assertStatus(200);
    }

    // --- accept: invalid/expired token ---------------------------------------

    public function test_accept_returns_404_for_nonexistent_token(): void
    {
        $response = $this->postJson('/api/v1/invitations/ghost_token_000/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ]);

        $response->assertStatus(404);
    }

    public function test_accept_returns_404_for_expired_token(): void
    {
        $pending = $this->createPendingInvitation([
            'invitation_token' => 'expired_accept_token',
            'invitation_expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/invitations/expired_accept_token/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'invitation.invalid_link');
    }

    // ===========================================================================
    // Full flow integration: invite → verify → accept
    // ===========================================================================

    public function test_complete_invitation_flow_from_send_to_auto_login(): void
    {
        Notification::fake();

        // 1. Admin sends invitation
        $franchise = $this->createFranchise();
        $adminSm = $this->createAdminSm(['email' => 'admin_sender@test.com', 'sm_franchise_id' => $franchise->id]);
        $sendResp = $this->actingAs($adminSm)
            ->postJson('/api/v1/invitations', [
                'name' => 'Nuevo Usuario',
                'email' => 'nuevo@test.com',
                'role' => Role::SB_OWNER,
                'company_name' => 'Nuevo LLC',
            ]);

        $sendResp->assertStatus(201);

        // Extract the raw activation URL (non-production returns it in the response)
        $activationUrl = $sendResp->json('data.activation_url');
        $this->assertNotNull($activationUrl, 'activation_url must be present in non-production.');

        // Extract token from URL: last segment after /invite/
        $token = last(explode('/', $activationUrl));
        $this->assertNotEmpty($token);

        // 2. Invited user verifies the token (public endpoint, no auth)
        $verifyResp = $this->getJson("/api/v1/invitations/{$token}/verify");

        $verifyResp->assertStatus(200);
        $verifyResp->assertJsonPath('data.email', 'n***@test.com');
        $verifyResp->assertJsonMissingPath('data.role');

        // 3. Invited user sets their password (public endpoint, no auth)
        $acceptResp = $this->postJson("/api/v1/invitations/{$token}/accept", [
            'password' => 'MiPassword99!',
            'password_confirmation' => 'MiPassword99!',
        ]);

        $acceptResp->assertStatus(200);
        $acceptResp->assertJsonPath('data.role', Role::SB_OWNER);

        // Sanctum token present for auto-login
        $sanctumToken = $acceptResp->json('data.token');
        $this->assertNotNull($sanctumToken);

        // 4. Verify database state after acceptance
        $user = User::where('email', 'nuevo@test.com')->first();
        $this->assertNull($user->invitation_token, 'Token must be cleared after acceptance.');
        $this->assertNotNull($user->invitation_accepted_at, 'accepted_at must be set.');
        $this->assertNotNull($user->email_verified_at, 'email_verified_at must be set.');
        $this->assertTrue($user->hasRole(Role::SB_OWNER), 'Role must be assigned.');

        // 5. Token no longer valid (cannot verify or accept twice)
        $this->getJson("/api/v1/invitations/{$token}/verify")
            ->assertStatus(404);

        // 6. Invitation no longer appears in pending list
        $indexResp = $this->actingAs($adminSm)->getJson('/api/v1/invitations');
        $indexResp->assertJsonCount(0, 'data');
    }

    // ===========================================================================
    // Tenant isolation — index()
    // ===========================================================================

    public function test_send_assigns_inviter_franchise_to_new_user(): void
    {
        Notification::fake();
        $franchise = $this->createFranchise();
        $adminSm = $this->createAdminSm(['sm_franchise_id' => $franchise->id]);

        $this->actingAs($adminSm)
            ->postJson('/api/v1/invitations', $this->validInvitePayload(['email' => 'franchise_check@test.com']));

        $this->assertDatabaseHas('users', [
            'email' => 'franchise_check@test.com',
            'sm_franchise_id' => $franchise->id,
        ]);
    }

    public function test_admin_sm_only_sees_own_franchise_invitations(): void
    {
        $franchiseA = $this->createFranchise();
        $franchiseB = $this->createFranchise();
        $adminSm = $this->createAdminSm(['sm_franchise_id' => $franchiseA->id]);

        // Own franchise
        $this->createPendingInvitation(['email' => 'own@test.com', 'sm_franchise_id' => $franchiseA->id]);
        // Other franchise
        $this->createPendingInvitation(['email' => 'other@test.com', 'sm_franchise_id' => $franchiseB->id]);

        $response = $this->actingAs($adminSm)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.email', 'own@test.com');
    }

    public function test_superadmin_sees_all_invitations_across_franchises(): void
    {
        $superadmin = $this->createSuperadmin();
        $franchiseA = $this->createFranchise();
        $franchiseB = $this->createFranchise();

        $this->createPendingInvitation(['email' => 'franchise_a@test.com', 'sm_franchise_id' => $franchiseA->id]);
        $this->createPendingInvitation(['email' => 'franchise_b@test.com', 'sm_franchise_id' => $franchiseB->id]);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/invitations');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    // ===========================================================================
    // Tenant isolation — resend() and destroy()
    // ===========================================================================

    public function test_admin_sm_cannot_resend_other_franchise_invitation(): void
    {
        Notification::fake();
        $franchiseA = $this->createFranchise();
        $franchiseB = $this->createFranchise();
        $adminSm = $this->createAdminSm(['sm_franchise_id' => $franchiseA->id]);
        $pending = $this->createPendingInvitation([
            'email' => 'other_resend@test.com',
            'sm_franchise_id' => $franchiseB->id,
        ]);

        $response = $this->actingAs($adminSm)
            ->postJson("/api/v1/invitations/{$pending->id}/resend");

        $response->assertStatus(403);
    }

    public function test_admin_sm_cannot_revoke_other_franchise_invitation(): void
    {
        $franchiseA = $this->createFranchise();
        $franchiseB = $this->createFranchise();
        $adminSm = $this->createAdminSm(['sm_franchise_id' => $franchiseA->id]);
        $pending = $this->createPendingInvitation([
            'email' => 'other_revoke@test.com',
            'sm_franchise_id' => $franchiseB->id,
        ]);

        $response = $this->actingAs($adminSm)
            ->deleteJson("/api/v1/invitations/{$pending->id}");

        $response->assertStatus(403);
    }

    // ===========================================================================
    // Token revocation on accept and revoke
    // ===========================================================================

    public function test_accept_revokes_preexisting_sanctum_tokens(): void
    {
        $pending = $this->createPendingInvitation(['invitation_token' => 'revoke_old_tokens']);
        $pending->assignRole(Role::SB_OWNER);

        // Create a stale token for this user (simulates a previous partial accept)
        $pending->createToken('stale');

        $this->postJson('/api/v1/invitations/revoke_old_tokens/accept', [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ]);

        // Only the newly issued token should remain
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_revoke_deletes_sanctum_tokens(): void
    {
        $superadmin = $this->createSuperadmin();
        $pending = $this->createPendingInvitation(['email' => 'token_revoke@test.com']);

        // Give the pending user a token
        $pending->createToken('test-token');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->actingAs($superadmin)
            ->deleteJson("/api/v1/invitations/{$pending->id}");

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ===========================================================================
    // Role::invitable()
    // ===========================================================================

    public function test_role_invitable_excludes_superadmin(): void
    {
        $this->assertNotContains(Role::SUPERADMIN, Role::invitable());
    }

    public function test_role_invitable_covers_all_non_superadmin_constants(): void
    {
        $constants = (new \ReflectionClass(Role::class))->getConstants();
        $expected = array_values(array_filter($constants, fn ($v) => $v !== Role::SUPERADMIN));
        sort($expected);
        $actual = Role::invitable();
        sort($actual);

        $this->assertSame($expected, $actual,
            'Role::invitable() is out of sync with the class constants. '
            .'Add the missing role(s) to invitable().'
        );
    }

    // ===========================================================================
    // WILT-71 replanteado — sm_franchise_id in invitation payload
    // ===========================================================================

    public function test_superadmin_can_invite_admin_sm_to_specific_franchise(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();
        $franchise = $this->createFranchise();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', [
                'name' => 'Admin For Franchise',
                'email' => 'newadmin@test.com',
                'role' => Role::ADMIN_SM,
                'sm_franchise_id' => $franchise->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@test.com',
            'sm_franchise_id' => $franchise->id,
        ]);
    }

    public function test_cannot_invite_admin_sm_without_franchise_id(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', [
                'name' => 'Admin No Franchise',
                'email' => 'admin_nofr@test.com',
                'role' => Role::ADMIN_SM,
                // no sm_franchise_id
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sm_franchise_id']);
    }

    public function test_cannot_invite_with_duplicate_accepted_email_across_franchises(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();
        $franchiseA = $this->createFranchise();
        $franchiseB = $this->createFranchise();

        // Active user already accepted in franchise A
        User::factory()->create([
            'email' => 'duplicate@test.com',
            'invitation_accepted_at' => now()->subDay(),
            'invitation_token' => null,
            'sm_franchise_id' => $franchiseA->id,
        ]);

        // Try to invite the same email to franchise B
        $response = $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', [
                'name' => 'Dup User',
                'email' => 'duplicate@test.com',
                'role' => Role::SB_OWNER,
                'sm_franchise_id' => $franchiseB->id,
                'company_name' => 'Dup LLC',
            ]);

        // Email is globally unique — must be rejected regardless of franchise
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_invited_admin_sm_retains_franchise_after_accepting(): void
    {
        Notification::fake();
        $superadmin = $this->createSuperadmin();
        $franchise = $this->createFranchise();

        // Send invitation with explicit franchise
        $this->actingAs($superadmin)
            ->postJson('/api/v1/invitations', [
                'name' => 'Accept Admin',
                'email' => 'accept.admin@test.com',
                'role' => Role::ADMIN_SM,
                'sm_franchise_id' => $franchise->id,
            ]);

        $user = User::where('email', 'accept.admin@test.com')->first();

        // Accept the invitation
        $this->postJson("/api/v1/invitations/{$user->invitation_token}/accept", [
            'password' => 'MySecure123!',
            'password_confirmation' => 'MySecure123!',
        ])->assertOk();

        // Franchise link must survive the acceptance flow
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'sm_franchise_id' => $franchise->id,
            'invitation_token' => null,
        ]);
        $this->assertNotNull($user->fresh()->invitation_accepted_at);
    }
}

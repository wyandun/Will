<?php

namespace Tests\Unit;

use App\Enums\Role;
use PHPUnit\Framework\TestCase;

class RoleInvitableByRoleTest extends TestCase
{
    public function test_superadmin_can_invite_all_invitable_roles(): void
    {
        $this->assertEqualsCanonicalizing(
            Role::invitable(),
            Role::invitableByRole(Role::SUPERADMIN),
        );
    }

    public function test_system_admin_can_invite_readonly_and_admin_sm(): void
    {
        $this->assertEqualsCanonicalizing(
            [Role::SYSTEM_ADMIN_READONLY, Role::ADMIN_SM],
            Role::invitableByRole(Role::SYSTEM_ADMIN),
        );
    }

    public function test_admin_sm_can_invite_sb_owner_and_sub_franchise_owner(): void
    {
        $this->assertEqualsCanonicalizing(
            [Role::SB_OWNER, Role::SUB_FRANCHISE_OWNER],
            Role::invitableByRole(Role::ADMIN_SM),
        );
    }

    public function test_sb_owner_can_invite_sb_employee(): void
    {
        $this->assertSame(
            [Role::SB_EMPLOYEE],
            Role::invitableByRole(Role::SB_OWNER),
        );
    }

    public function test_sub_franchise_owner_can_invite_sub_franchise_admin(): void
    {
        $this->assertSame(
            [Role::SUB_FRANCHISE_ADMIN],
            Role::invitableByRole(Role::SUB_FRANCHISE_OWNER),
        );
    }

    public function test_system_admin_readonly_cannot_invite_anyone(): void
    {
        $this->assertSame([], Role::invitableByRole(Role::SYSTEM_ADMIN_READONLY));
    }

    public function test_sb_employee_cannot_invite_anyone(): void
    {
        $this->assertSame([], Role::invitableByRole(Role::SB_EMPLOYEE));
    }

    public function test_bb_employee_cannot_invite_anyone(): void
    {
        $this->assertSame([], Role::invitableByRole(Role::BB_EMPLOYEE));
    }

    public function test_sub_franchise_admin_cannot_invite_anyone(): void
    {
        $this->assertSame([], Role::invitableByRole(Role::SUB_FRANCHISE_ADMIN));
    }

    public function test_unknown_role_cannot_invite_anyone(): void
    {
        $this->assertSame([], Role::invitableByRole('unknown_role'));
    }
}

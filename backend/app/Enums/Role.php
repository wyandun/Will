<?php

namespace App\Enums;

/**
 * Domain role constants used across policies, services, and seeders.
 *
 * Centralises the magic strings so renaming a role is a single-file change.
 */
final class Role
{
    public const SUPERADMIN = 'superadmin';

    public const SYSTEM_ADMIN = 'system_admin';

    public const SYSTEM_ADMIN_READONLY = 'system_admin_readonly';

    public const ADMIN_SM = 'admin_sm';

    public const SB_OWNER = 'sb_owner';

    public const SB_EMPLOYEE = 'sb_employee';

    public const BB_EMPLOYEE = 'bb_employee';

    public const SUB_FRANCHISE_OWNER = 'sub_franchise_owner';

    public const SUB_FRANCHISE_ADMIN = 'sub_franchise_admin';

    /**
     * Prevent instantiation.
     */
    private function __construct() {}

    /**
     * All roles that may be assigned via an invitation.
     * SUPERADMIN is excluded — it is never assigned through the invitation flow.
     *
     * @return list<string>
     */
    public static function invitable(): array
    {
        return [
            self::SYSTEM_ADMIN,
            self::SYSTEM_ADMIN_READONLY,
            self::ADMIN_SM,
            self::SB_OWNER,
            self::SB_EMPLOYEE,
            self::BB_EMPLOYEE,
            self::SUB_FRANCHISE_OWNER,
            self::SUB_FRANCHISE_ADMIN,
        ];
    }
}

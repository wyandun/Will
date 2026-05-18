/**
 * Canonical role identifiers used across the application.
 * Keep in sync with backend Spatie roles.
 */
export const ROLES = /** @type {const} */ ({
  SUPERADMIN:              'superadmin',
  SYSTEM_ADMIN:            'system_admin',
  SYSTEM_ADMIN_READONLY:   'system_admin_readonly',
  ADMIN_SM:                'admin_sm',
  SB_OWNER:                'sb_owner',
  SB_EMPLOYEE:             'sb_employee',
  BB_EMPLOYEE:             'bb_employee',
  SUB_FRANCHISE_OWNER:     'sub_franchise_owner',
  SUB_FRANCHISE_ADMIN:     'sub_franchise_admin',
});

/**
 * Roles that can be invited from the global Users / Invitations page.
 * These are system-level roles that do not require a franchise context.
 */
export const SYSTEM_INVITABLE_ROLES = [
  ROLES.SYSTEM_ADMIN,
  ROLES.SYSTEM_ADMIN_READONLY,
];

/**
 * Roles that can be invited from a specific SM Franchise detail page.
 * These roles are always scoped to a franchise.
 */
export const FRANCHISE_INVITABLE_ROLES = [
  ROLES.ADMIN_SM,
  ROLES.SB_OWNER,
  ROLES.SB_EMPLOYEE,
  ROLES.BB_EMPLOYEE,
  ROLES.SUB_FRANCHISE_OWNER,
  ROLES.SUB_FRANCHISE_ADMIN,
];

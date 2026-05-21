import { useAuthStore } from '../store/authStore';

/**
 * Hook that exposes permission helpers for the current user.
 *
 * Usage:
 *   const { canWrite, isReadonly } = usePermissions();
 *   if (canWrite('feed')) { show create button }
 */
export function usePermissions() {
  const role = useAuthStore((s) => s.role);
  const permissions = useAuthStore((s) => s.permissions);

  const isReadonly = role === 'system_admin_readonly';

  const permMap = {};
  if (Array.isArray(permissions)) {
    permissions.forEach((p) => { permMap[p.module] = p; });
  }

  /**
   * Returns true if the current user has write access to the given module.
   * Superadmin and system_admin always have write access.
   * system_admin_readonly never has write access.
   * Other roles check the can_write flag from their permissions.
   */
  const canWrite = (module) => {
    if (role === 'superadmin' || role === 'system_admin') return true;
    if (role === 'system_admin_readonly') return false;
    return permMap[module]?.can_write === true;
  };

  return { canWrite, isReadonly, role };
}

import { useAuthStore } from '../store/authStore';

/**
 * Hook for permission-aware UI decisions.
 *
 * - `canWrite(module)` — true if the user can create/edit/delete in the module.
 * - `isReadonly` — true when the role is system_admin_readonly.
 * - `role` — current role string for ad-hoc checks.
 */
export function usePermissions() {
  const role = useAuthStore((s) => s.role);
  const permissions = useAuthStore((s) => s.permissions);

  const permMap = {};
  if (Array.isArray(permissions)) {
    permissions.forEach((p) => { permMap[p.module] = p; });
  }

  const isReadonly = role === 'system_admin_readonly';

  const canWrite = (module) => {
    if (role === 'superadmin' || role === 'system_admin') return true;
    if (role === 'system_admin_readonly') return false;
    return permMap[module]?.can_write === true;
  };

  return { canWrite, isReadonly, role };
}

import { Navigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';

/**
 * Wraps any route that requires authentication.
 * Redirects to /login when no valid token is present in the store.
 *
 * IMPORTANT — local state only:
 * This guard reads `isAuthenticated` from Zustand, which reflects the
 * token stored in memory. It does NOT hit the server to confirm the token
 * is still valid (e.g. after revocation or session expiry).
 *
 * Server-side token verification is performed by `useAuthVerify` inside
 * `AuthenticatedLayout`, which calls GET /api/v1/me on mount and clears
 * the store if the token is rejected.
 *
 * Therefore this component MUST always be used together with
 * AuthenticatedLayout. Using ProtectedRoute alone (e.g. wrapping a page
 * that renders outside AuthenticatedLayout) will skip server verification
 * and allow a revoked token to access protected content until the next
 * full page reload.
 */
export default function ProtectedRoute({ children }) {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return children;
}

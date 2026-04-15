import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { authApi } from '../api/auth';
import { useAuthStore } from '../store/authStore';

/**
 * Verifies the stored token against the server on mount.
 * - On success: refreshes user/role/permissions in the store.
 * - On 401: clears auth state and redirects to /login.
 * Returns { loading } — callers should render a spinner while loading is true.
 */
export function useAuthVerify() {
  const [loading, setLoading] = useState(true);
  const setAuth = useAuthStore((s) => s.setAuth);
  const clearAuth = useAuthStore((s) => s.clearAuth);
  const navigate = useNavigate();

  useEffect(() => {
    let cancelled = false;

    async function verify() {
      try {
        const data = await authApi.getMe();
        if (!cancelled) {
          setAuth(data);
        }
      } catch (error) {
        if (!cancelled) {
          const status = error?.response?.status;
          if (status === 401) {
            clearAuth();
            navigate('/login', { replace: true });
          }
          // Any other network error: leave the stored state as-is so the
          // user is not logged out on a transient connection failure.
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    verify();

    return () => {
      cancelled = true;
    };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return { loading };
}

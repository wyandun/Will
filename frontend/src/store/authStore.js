import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { setTokenGetter } from '../api/client';

const useAuthStore = create(
  persist(
    (set) => ({
      user: null,
      token: null,
      role: null,
      permissions: [],
      isAuthenticated: false,

      setAuth: ({ user, token, role, permissions }) => {
        set((state) => ({
          user,
          token: token ?? state.token,
          role,
          permissions,
          isAuthenticated: true,
        }));
      },

      updateUser: (updates) => set((state) => ({ user: { ...state.user, ...updates } })),

      clearAuth: () => {
        set({ user: null, token: null, role: null, permissions: [], isAuthenticated: false });
      },
    }),
    {
      name: 'sm-portal-auth',
      // Only persist the token; the rest is restored from the API on next
      // visit if needed. For this phase we persist everything for convenience.
      partialize: (state) => ({
        user: state.user,
        token: state.token,
        role: state.role,
        permissions: state.permissions,
        isAuthenticated: state.isAuthenticated,
      }),
    }
  )
);

// Wire the token getter into the axios client so the interceptor can read
// the current token without creating a circular import.
setTokenGetter(() => useAuthStore.getState().token);

export { useAuthStore };

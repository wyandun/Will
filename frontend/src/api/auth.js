import apiClient from './client';

export const authApi = {
  /**
   * Authenticate with email + password.
   * Returns the full `data` object from the API response.
   */
  login: async (email, password) => {
    const response = await apiClient.post('/auth/login', { email, password });
    return response.data.data; // { user, token, role, permissions }
  },

  /**
   * Invalidate the current session token on the server.
   */
  logout: async () => {
    await apiClient.post('/auth/logout');
  },

  /**
   * Fetch the currently authenticated user from the server.
   * Used on app mount to verify the stored token is still valid and
   * refresh user/role/permissions from the source of truth.
   * Returns { user, token, role, permissions } or throws on 401.
   */
  getMe: async () => {
    const { data } = await apiClient.get('/auth/me');
    return data.data; // { user, token, role, permissions }
  },
};

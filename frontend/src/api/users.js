import apiClient from './client';

export const usersApi = {
  // Lightweight search used by the calendar "Add Guests" picker.
  // Returns up to 10 matches; backend scopes results to the auth user's franchise.
  search: (q) =>
    apiClient.get('/users/search', { params: { q } }).then((res) => res.data.data ?? []),
};

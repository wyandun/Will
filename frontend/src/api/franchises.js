import apiClient from './client';

export const franchisesApi = {
  /**
   * Retrieve all franchises visible to the current user.
   * Superadmin sees all; admin_sm sees only their own.
   */
  getFranchises: () =>
    apiClient.get('/franchises').then((res) => ({
      data: res.data.data ?? [],
      meta: res.data.meta ?? null,
    })),

  /**
   * Create a new franchise.
   * @param {Object} data - franchise fields
   */
  createFranchise: (data) =>
    apiClient.post('/franchises', data).then((res) => res.data.data),

  /**
   * Update an existing franchise by ID.
   * @param {number} id
   * @param {Object} data - partial or full franchise fields
   */
  updateFranchise: (id, data) =>
    apiClient.put(`/franchises/${id}`, data).then((res) => res.data.data),

  /**
   * Toggle franchise active/inactive status.
   * @param {number} id
   */
  toggleFranchiseStatus: (id) =>
    apiClient.patch(`/franchises/${id}/toggle-status`).then((res) => res.data.data),

  /**
   * Permanently delete a franchise by ID.
   * @param {number} id
   */
  deleteFranchise: (id) =>
    apiClient.delete(`/franchises/${id}`).then((res) => res.data),

  /**
   * Get a single franchise by ID.
   * @param {number} id
   */
  getFranchise: (id) =>
    apiClient.get(`/franchises/${id}`).then((res) => res.data.data),

  /**
   * Get all members (admins + clients) of a franchise.
   * @param {number} id
   */
  getMembers: (id) =>
    apiClient.get(`/franchises/${id}/members`).then((res) => res.data.data),

};
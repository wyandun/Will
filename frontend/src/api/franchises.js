import apiClient from './client';

export const franchisesApi = {
  /**
   * Retrieve all franchises visible to the current user.
   * Superadmin sees all; admin_sm sees only their own.
   */
  getFranchises: () =>
    apiClient.get('/franchises').then((res) => res.data.data),

  /**
   * Create a new franchise.
   * @param {Object} data - { name, type, region?, address?, phone? }
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
   * Permanently delete a franchise by ID.
   * @param {number} id
   */
  deleteFranchise: (id) =>
    apiClient.delete(`/franchises/${id}`).then((res) => res.data),
};

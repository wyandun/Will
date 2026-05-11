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
   * Retrieve a single franchise by ID (includes admins_count + clients_count).
   * @param {number|string} id
   */
  getFranchise: (id) =>
    apiClient.get(`/franchises/${id}`).then((res) => res.data.data),

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
   * Get all members of a franchise: { admins: [...], clients: [...] }.
   * admins = admin_sm users; clients = sb_owner + bb_employee users.
   * @param {number|string} id
   */
  getMembers: (id) =>
    apiClient.get(`/franchises/${id}/members`).then((res) => res.data.data),

  /**
   * Create a new admin_sm user for a franchise.
   * @param {number|string} id
   * @param {{ name, email, password, area, phone?, position? }} data
   */
  addAdmin: (id, data) =>
    apiClient.post(`/franchises/${id}/admins`, data).then((res) => res.data.data),

  /**
   * Create a new client user (sb_owner or bb_employee) for a franchise.
   * @param {number|string} id
   * @param {{ name, email, password, client_type: 'owner'|'investor', phone?, position? }} data
   */
  addClient: (id, data) =>
    apiClient.post(`/franchises/${id}/clients`, data).then((res) => res.data.data),
};
import apiClient from './client';

export const companiesApi = {
  /**
   * Retrieve all companies visible to the current user.
   * @param {Object} [params] - Optional query params
   * @param {number} [params.franchise_id] - Filter by franchise (superadmin only)
   */
  getCompanies: (params = {}) =>
    apiClient.get('/companies', { params }).then((res) => ({
      data: res.data.data,
      meta: res.data.meta,
    })),

  /**
   * Retrieve companies for a specific franchise (for assignment modals).
   * @param {number} franchiseId
   * @returns {Promise<Array>}
   */
  getCompaniesByFranchise: (franchiseId) =>
    apiClient
      .get('/companies', { params: { franchise_id: franchiseId } })
      .then((res) => (Array.isArray(res.data.data) ? res.data.data : [])),

  /**
   * Create a new company via the Close Deal flow.
   * @param {Object} data - { name, sm_franchise_id, industry?, address?, phone?, email?, website?, city?, state?, country?, notes? }
   */
  createCompany: (data) =>
    apiClient.post('/companies/close-deal', data).then((res) => res.data.data),

  /**
   * Update an existing company by ID.
   * @param {number} id
   * @param {Object} data - partial or full company fields
   */
  updateCompany: (id, data) =>
    apiClient.put(`/companies/${id}`, data).then((res) => res.data.data),

  /**
   * Permanently delete a company by ID.
   * @param {number} id
   */
  deleteCompany: (id) =>
    apiClient.delete(`/companies/${id}`).then((res) => res.data),
};

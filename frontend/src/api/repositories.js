import apiClient from './client';

/**
 * API client for the Document Repositories module.
 * Endpoints under /api/v1/repositories manage company-level document repositories.
 */
export const repositoriesApi = {
  /**
   * List all repositories visible to the current user.
   * Superadmin sees all; admin_sm sees only their franchise's repos.
   */
  list: () =>
    apiClient.get('/repositories').then((res) => res.data.data ?? []),

  /**
   * Create a new repository linked to a company.
   * @param {{ company_id: number, sub_franchise_id?: number }} data
   */
  create: (data) =>
    apiClient.post('/repositories', data).then((res) => res.data.data),

  /**
   * Get a single repository with full detail.
   * @param {number} id
   */
  show: (id) =>
    apiClient.get(`/repositories/${id}`).then((res) => res.data.data),

  /**
   * Permanently delete a repository.
   * @param {number} id
   */
  delete: (id) =>
    apiClient.delete(`/repositories/${id}`).then((res) => res.data),

  /**
   * Get the process documents tree for a repository.
   * Returns the ProcessMap categories with their processes, sub-processes and
   * documents, or null when the company has no associated ProcessMap.
   * @param {number} id
   */
  getProcessDocuments: (id) =>
    apiClient.get(`/repositories/${id}/process-documents`).then((res) => res.data.data),

  /**
   * List companies available to the current user (for the create-repository dropdown).
   * The /companies endpoint returns a paginated response; we extract the data array.
   */
  listCompanies: () =>
    apiClient.get('/companies').then((res) => {
      const raw = res.data.data;
      // Paginated response: { data: [...], meta: {...} }
      if (raw && Array.isArray(raw.data)) return raw.data;
      // Non-paginated response: data is the array directly
      if (Array.isArray(raw)) return raw;
      return [];
    }),
};

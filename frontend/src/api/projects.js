import apiClient from './client';

/**
 * API client for the Tracking / Projects module.
 * Endpoints under /api/v1/projects.
 */
export const projectsApi = {
  /**
   * List projects visible to the current user.
   * superadmin sees all; admin_sm sees only their franchise.
   *
   * Supports optional filters:
   *   - search: string — filters by company name or catalog item name (ILIKE)
   *   - status: 'active'|'completed'|'paused'|'cancelled'
   *
   * @param {Object} [params]
   * @param {string} [params.search]
   * @param {string} [params.status]
   * @returns {Promise<Array>}
   */
  getProjects: (params = {}) => {
    const query = {};
    if (params.search) query.search = params.search;
    if (params.status) query.status = params.status;
    return apiClient.get('/projects', { params: query }).then((res) => res.data.data ?? []);
  },

  /**
   * Get a single project by ID, including all generated deliverables.
   *
   * @param {number} id
   * @returns {Promise<Object>}
   */
  getProject: (id) =>
    apiClient.get(`/projects/${id}`).then((res) => res.data.data),

  /**
   * Create a project and auto-generate its deliverables schedule.
   *
   * @param {Object} data
   * @param {number} data.company_id
   * @param {number} data.franchise_id
   * @param {number} data.catalog_item_id
   * @param {'bundle'|'service'|'deliverable'} data.type
   * @param {string} data.start_date  ISO date string (YYYY-MM-DD)
   * @param {string} [data.notes]
   * @returns {Promise<Object>}
   */
  createProject: (data) =>
    apiClient.post('/projects', data).then((res) => res.data.data),

  /**
   * Update the status of a single project deliverable.
   * Returns the updated deliverable plus recalculated project KPIs.
   *
   * @param {number} projectId
   * @param {number} deliverableId
   * @param {'pending'|'in_progress'|'completed'|'blocked'} status
   * @returns {Promise<{ deliverable: Object, progress_percentage: number, deliverables_completed: number, deliverables_total: number }>}
   */
  updateDeliverableStatus: (projectId, deliverableId, status) =>
    apiClient
      .patch(`/projects/${projectId}/deliverables/${deliverableId}`, { status })
      .then((res) => res.data.data),
};

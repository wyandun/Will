import apiClient from './client';

/**
 * API client for the Service Catalog module.
 * Endpoints under /api/v1/catalog-items expose a 3-level hierarchy:
 *   bundle (paquete) > service (servicio) > deliverable (entregable)
 */
export const catalogApi = {
  /**
   * Retrieve the full catalog tree in a single call.
   * Returns { bundles, services, counts: { bundles, services, deliverables } }.
   */
  getTree: () =>
    apiClient.get('/catalog-items/tree').then((res) => res.data.data ?? {}),

  /**
   * List catalog items filtered by hierarchy level.
   * Returns a flat list. Useful to detect deliverables whose parent_id is null
   * (orphans) or whose parent no longer exists in the tree.
   * @param {'bundle'|'service'|'deliverable'} level
   */
  listByLevel: (level) =>
    apiClient
      .get('/catalog-items', { params: { level } })
      .then((res) => (Array.isArray(res.data.data) ? res.data.data : [])),

  /**
   * Create a new catalog item (any level).
   * @param {Object} data - { level, name_es, name_en, ...level-specific fields }
   */
  createItem: (data) =>
    apiClient.post('/catalog-items', data).then((res) => res.data.data),

  /**
   * Update an existing catalog item by ID.
   * @param {number} id
   * @param {Object} data - partial or full item fields
   */
  updateItem: (id, data) =>
    apiClient.put(`/catalog-items/${id}`, data).then((res) => res.data.data),

  /**
   * Permanently delete a catalog item by ID.
   * Backend should cascade according to business rules.
   * @param {number} id
   * @param {boolean} [cascadeChildren=false] - When true, also delete child items
   *        (otherwise children are left orphaned with parent_id = null).
   */
  deleteItem: (id, cascadeChildren = false) =>
    apiClient
      .delete(`/catalog-items/${id}${cascadeChildren ? '?cascade_children=true' : ''}`)
      .then((res) => res.data),
};

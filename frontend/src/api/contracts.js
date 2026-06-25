import apiClient from './client';

/**
 * Contracts API module. Mirrors the backend ContractController:
 *   - index returns a paginated resource collection → { data, meta, links }
 *   - store/show/update/send/sync return { success, data: ContractResource }
 *   - templates returns the DocuSeal template list
 */
export const contractsApi = {
  list: (params = {}) => apiClient.get('/contracts', { params }).then((r) => r.data),
  get: (id) => apiClient.get(`/contracts/${id}`).then((r) => r.data.data),
  create: (payload) => apiClient.post('/contracts', payload).then((r) => r.data.data),
  update: (id, payload) => apiClient.put(`/contracts/${id}`, payload).then((r) => r.data.data),
  remove: (id) => apiClient.delete(`/contracts/${id}`).then((r) => r.data),
  send: (id, payload) => apiClient.post(`/contracts/${id}/send`, payload).then((r) => r.data.data),
  sync: (id) => apiClient.post(`/contracts/${id}/sync`).then((r) => r.data.data),
  templates: () => apiClient.get('/docuseal/templates').then((r) => r.data?.data ?? r.data ?? []),
};

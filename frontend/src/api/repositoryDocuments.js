import apiClient from './client';

export const repositoryDocumentsApi = {
  /**
   * List documents for a repository section.
   * @param {number} repositoryId
   * @param {{ section?: string, category?: string }} params
   */
  list: (repositoryId, params = {}) =>
    apiClient
      .get(`/repositories/${repositoryId}/documents`, { params })
      .then((res) => res.data.data ?? []),

  /**
   * Upload a document to a repository.
   * Uses multipart/form-data for file upload.
   * @param {number} repositoryId
   * @param {FormData} formData - fields: file, title, description, section, setup_category
   */
  upload: (repositoryId, formData) =>
    apiClient
      .post(`/repositories/${repositoryId}/documents`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      .then((res) => res.data.data),

  /**
   * Delete a document from a repository.
   * @param {number} repositoryId
   * @param {number} documentId
   */
  delete: (repositoryId, documentId) =>
    apiClient
      .delete(`/repositories/${repositoryId}/documents/${documentId}`)
      .then((res) => res.data),
};

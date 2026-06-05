import apiClient from './client';

export const processMapsApi = {
  list: (params = {}) =>
    apiClient.get('/process-maps', { params }).then((res) => res.data),

  get: (id) =>
    apiClient.get(`/process-maps/${id}`).then((res) => res.data),

  create: (payload) =>
    apiClient.post('/process-maps', payload).then((res) => res.data),

  delete: (id) =>
    apiClient.delete(`/process-maps/${id}`).then((res) => res.data),

  updateCategory: (categoryId, data) =>
    apiClient.patch(`/process-categories/${categoryId}`, data).then((res) => res.data),

  createProcess: (categoryId, data) =>
    apiClient.post(`/process-categories/${categoryId}/processes`, data).then((res) => res.data),

  updateProcess: (id, data) =>
    apiClient.put(`/processes/${id}`, data).then((res) => res.data),

  deleteProcess: (id) =>
    apiClient.delete(`/processes/${id}`).then((res) => res.data),

  createSubProcess: (processId, data) =>
    apiClient.post(`/processes/${processId}/sub-processes`, data).then((res) => res.data),

  updateSubProcess: (id, data) =>
    apiClient.put(`/sub-processes/${id}`, data).then((res) => res.data),

  deleteSubProcess: (id) =>
    apiClient.delete(`/sub-processes/${id}`).then((res) => res.data),

  createSubSubProcess: (subProcessId, data) =>
    apiClient.post(`/sub-processes/${subProcessId}/sub-sub-processes`, data).then((res) => res.data),

  updateSubSubProcess: (id, data) =>
    apiClient.put(`/sub-sub-processes/${id}`, data).then((res) => res.data),

  deleteSubSubProcess: (id) =>
    apiClient.delete(`/sub-sub-processes/${id}`).then((res) => res.data),

  // ── Diagram detail (BPMN + documents) ───────────────────────────────────
  getSubProcess: (id) =>
    apiClient.get(`/sub-processes/${id}`).then((res) => res.data),

  getSubSubProcess: (id) =>
    apiClient.get(`/sub-sub-processes/${id}`).then((res) => res.data),

  uploadSubProcessBpmn: (id, lang, bpmnXml) =>
    apiClient.put(`/sub-processes/${id}/bpmn`, { lang, bpmn_xml: bpmnXml }).then((res) => res.data),

  uploadSubSubProcessBpmn: (id, lang, bpmnXml) =>
    apiClient.put(`/sub-sub-processes/${id}/bpmn`, { lang, bpmn_xml: bpmnXml }).then((res) => res.data),

  addSubProcessDocument: (id, formData) =>
    apiClient
      .post(`/sub-processes/${id}/documents`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then((res) => res.data),

  addSubSubProcessDocument: (id, formData) =>
    apiClient
      .post(`/sub-sub-processes/${id}/documents`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then((res) => res.data),

  updateDocument: (id, formData) =>
    apiClient
      .post(`/process-documents/${id}`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then((res) => res.data),

  deleteDocument: (id) =>
    apiClient.delete(`/process-documents/${id}`).then((res) => res.data),
};

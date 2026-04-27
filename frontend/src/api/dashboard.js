import apiClient from './client';

const get = (path) => apiClient.get(path).then((r) => r.data.data);

export const dashboardApi = {
  getKpis: () => get('/dashboard/kpis'),
  getFeed: () => get('/dashboard/feed'),
  getEvents: () => get('/dashboard/events'),
  getTracking: () => get('/dashboard/tracking'),
  getContracts: () => get('/dashboard/contracts'),
  getDocuments: () => get('/dashboard/documents'),
  getProcessMaps: () => get('/dashboard/process-maps'),
};

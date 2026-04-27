import apiClient from './client';

export const dashboardApi = {
  getKpis: () => apiClient.get('/dashboard/kpis').then((r) => r.data.data),
};

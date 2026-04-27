import apiClient from './client';

export const dashboardApi = {
  getAll: () => apiClient.get('/dashboard').then(r => r.data.data),
};

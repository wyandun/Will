import apiClient from './client';

export const dashboardApi = {
  getAll: () => apiClient.get('/dashboard').then(r => r.data.data),
  getEvents: () => apiClient.get('/dashboard/events').then(r => r.data.data),
};

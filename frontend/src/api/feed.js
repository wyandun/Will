import apiClient from './client';

export const feedApi = {
  getPosts: (params = {}) => apiClient.get('/feed/posts', { params }),
  getPresence: () => apiClient.get('/feed/presence'),
};

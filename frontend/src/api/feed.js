import apiClient from './client';

export const feedApi = {
  getPosts: (params = {}) => apiClient.get('/feed/posts', { params }),
  getPresence: () => apiClient.get('/feed/presence'),
  createPost: (formData) =>
    apiClient.post('/feed/posts', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  updatePost: (id, formData) =>
    apiClient.put(`/feed/posts/${id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  deletePost: (id) => apiClient.delete(`/feed/posts/${id}`),
  reactToPost: (id, emoji) => apiClient.post(`/feed/posts/${id}/react`, { emoji }),
  getComments: (id, page = 1) => apiClient.get(`/feed/posts/${id}/comments`, { params: { page } }),
  addComment: (id, content) => apiClient.post(`/feed/posts/${id}/comments`, { content }),
  deleteComment: (commentId) => apiClient.delete(`/feed/comments/${commentId}`),
};

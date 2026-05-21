import apiClient from './client';

export const newsApi = {
  getArticles: (page = 1) => apiClient.get('/news/articles', { params: { page } }),
  fetchNews: () => apiClient.post('/news/fetch'),
  publishArticle: (id) => apiClient.post(`/news/articles/${id}/publish`),
  rejectArticle: (id) => apiClient.post(`/news/articles/${id}/reject`),
};

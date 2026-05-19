import apiClient from './client';

export const eventsApi = {
  getEvents: (params = {}) =>
    apiClient.get('/events', { params }).then((res) => ({
      data: res.data.data ?? [],
      meta: res.data.meta ?? null,
    })),

  getEvent: (id) =>
    apiClient.get(`/events/${id}`).then((res) => res.data.data),

  createEvent: (data) =>
    apiClient.post('/events', data).then((res) => res.data.data),

  updateEvent: (id, data) =>
    apiClient.put(`/events/${id}`, data).then((res) => res.data.data),

  deleteEvent: (id) =>
    apiClient.delete(`/events/${id}`).then((res) => res.data),
};

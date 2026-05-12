import client from './client';

export const eventsApi = {
  list:   ()          => client.get('/events').then(r => r.data.data),
  get:    (id)        => client.get(`/events/${id}`).then(r => r.data.data),
  create: (data)      => client.post('/events', data).then(r => r.data.data),
  update: (id, data)  => client.patch(`/events/${id}`, data).then(r => r.data.data),
  delete: (id)        => client.delete(`/events/${id}`),
};

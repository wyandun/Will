import apiClient from './client';

export const systemAdminsApi = {
  getSystemAdmins: () =>
    apiClient.get('/system-admins').then((res) => ({
      data: res.data.data ?? [],
    })),

  createSystemAdmin: (data) =>
    apiClient.post('/system-admins', data).then((res) => res.data.data),

  updateSystemAdmin: (id, data) =>
    apiClient.put(`/system-admins/${id}`, data).then((res) => res.data.data),

  deleteSystemAdmin: (id) =>
    apiClient.delete(`/system-admins/${id}`).then((res) => res.data),
};

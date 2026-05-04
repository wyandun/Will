import apiClient from './client';

export const profileApi = {
  getProfile: () => apiClient.get('/profile').then(r => r.data.data),
  updateProfile: (data) => apiClient.patch('/profile', data).then(r => r.data.data),
  updatePassword: (data) => apiClient.patch('/profile/password', data).then(r => r.data),
  uploadAvatar: (file) => {
    const form = new FormData();
    form.append('avatar', file);
    return apiClient.post('/profile/avatar', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then(r => r.data.data);
  },
};

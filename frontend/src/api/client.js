import axios from 'axios';

const apiClient = axios.create({
  baseURL: 'http://localhost/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// tokenGetter is set by authStore after it initialises, breaking the
// circular dependency that would occur if we imported the store here.
let tokenGetter = () => null;

export function setTokenGetter(fn) {
  tokenGetter = fn;
}

apiClient.interceptors.request.use((config) => {
  const token = tokenGetter();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default apiClient;

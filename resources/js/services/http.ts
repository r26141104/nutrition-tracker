import axios, { type AxiosInstance } from 'axios';

const TOKEN_KEY = 'auth.token';

export const tokenStorage = {
  get: (): string | null => localStorage.getItem(TOKEN_KEY),
  set: (token: string): void => localStorage.setItem(TOKEN_KEY, token),
  clear: (): void => localStorage.removeItem(TOKEN_KEY),
};

const http: AxiosInstance = axios.create({
  baseURL: '/api',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

// 每次發 request 自動帶 Bearer token
http.interceptors.request.use((config) => {
  const token = tokenStorage.get();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// 收到 401 → 清掉本地 token（讓 router guard 把使用者導去 login）
http.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      tokenStorage.clear();
    }
    return Promise.reject(error);
  },
);

export default http;

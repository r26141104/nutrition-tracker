import http from './http';

export interface User {
  id: number;
  name: string;
  email: string;
  created_at: string | null;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface LoginPayload {
  email: string;
  password: string;
}

export interface AuthResponse {
  user: User;
  token: string;
}

/**
 * 所有會員相關的 API 呼叫都集中在這裡，
 * Vue component 不要直接打 axios。
 */
export const authService = {
  register(payload: RegisterPayload): Promise<AuthResponse> {
    return http.post<AuthResponse>('/register', payload).then((r) => r.data);
  },

  login(payload: LoginPayload): Promise<AuthResponse> {
    return http.post<AuthResponse>('/login', payload).then((r) => r.data);
  },

  logout(): Promise<void> {
    return http.post('/logout').then(() => undefined);
  },

  getUser(): Promise<User> {
    return http.get<{ user: User }>('/user').then((r) => r.data.user);
  },
};

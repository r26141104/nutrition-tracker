import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
  authService,
  type LoginPayload,
  type RegisterPayload,
  type User,
} from '@/services/authService';
import { tokenStorage } from '@/services/http';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null);
  const bootstrapped = ref(false);

  const isAuthenticated = computed(() => user.value !== null);

  /**
   * 應用啟動時呼叫一次：若 localStorage 有 token，
   * 試著用它去抓使用者資料；失敗就清掉。
   */
  async function bootstrap(): Promise<void> {
    if (bootstrapped.value) return;
    const token = tokenStorage.get();
    if (token) {
      try {
        user.value = await authService.getUser();
      } catch {
        tokenStorage.clear();
        user.value = null;
      }
    }
    bootstrapped.value = true;
  }

  async function register(payload: RegisterPayload): Promise<void> {
    const result = await authService.register(payload);
    tokenStorage.set(result.token);
    user.value = result.user;
  }

  async function login(payload: LoginPayload): Promise<void> {
    const result = await authService.login(payload);
    tokenStorage.set(result.token);
    user.value = result.user;
  }

  async function logout(): Promise<void> {
    try {
      await authService.logout();
    } catch {
      // 即使後端失敗也清乾淨本地狀態
    }
    tokenStorage.clear();
    user.value = null;
  }

  return {
    user,
    bootstrapped,
    isAuthenticated,
    bootstrap,
    register,
    login,
    logout,
  };
});

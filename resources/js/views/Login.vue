<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { AxiosError } from 'axios';
import { useAuthStore } from '@/stores/authStore';

const router = useRouter();
const auth = useAuthStore();

const form = reactive({
  email: '',
  password: '',
});

const errors = ref<Record<string, string[]>>({});
const generalError = ref('');
const submitting = ref(false);

async function onSubmit(): Promise<void> {
  errors.value = {};
  generalError.value = '';
  submitting.value = true;
  try {
    await auth.login(form);
    router.push({ name: 'dashboard' });
  } catch (e) {
    if (e instanceof AxiosError && e.response?.status === 422) {
      errors.value = e.response.data?.errors ?? {};
      generalError.value = e.response.data?.message ?? '輸入有誤';
    } else {
      generalError.value = '登入失敗，請稍後再試';
    }
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="page">
    <div class="card">
      <h1>登入</h1>

      <p v-if="generalError" class="alert">{{ generalError }}</p>

      <form @submit.prevent="onSubmit" novalidate>
        <div class="field">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            :class="{ invalid: errors.email }"
          />
          <small v-if="errors.email" class="error">{{ errors.email[0] }}</small>
        </div>

        <div class="field">
          <label for="password">密碼</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            :class="{ invalid: errors.password }"
          />
          <small v-if="errors.password" class="error">{{ errors.password[0] }}</small>
        </div>

        <button type="submit" :disabled="submitting" class="btn">
          {{ submitting ? '登入中…' : '登入' }}
        </button>
      </form>

      <p class="hint">
        還沒有帳號？<RouterLink to="/register">前往註冊</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
.page { max-width: 420px; margin: 64px auto; padding: 0 24px; }
.card { padding: 32px; border: 1px solid #e2e8f0; border-radius: 12px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
h1 { margin: 0 0 24px; font-size: 1.5rem; color: #0f172a; }
.field { margin-bottom: 16px; }
label { display: block; font-size: 0.875rem; color: #475569; margin-bottom: 6px; }
input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem; }
input:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.15); }
input.invalid { border-color: #dc2626; }
.error { color: #dc2626; font-size: 0.8125rem; display: block; margin-top: 6px; }
.btn { width: 100%; background: #0ea5e9; color: white; border: 0; padding: 11px; border-radius: 8px; font-size: 1rem; cursor: pointer; margin-top: 8px; }
.btn:hover:not(:disabled) { background: #0284c7; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.alert { background: #fef2f2; color: #b91c1c; padding: 10px 12px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem; }
.hint { text-align: center; margin-top: 24px; color: #64748b; font-size: 0.875rem; }
.hint a { color: #0ea5e9; text-decoration: none; }
.hint a:hover { text-decoration: underline; }
</style>

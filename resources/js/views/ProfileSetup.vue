<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { AxiosError } from 'axios';
import {
  profileService,
  type ActivityLevel,
  type GoalType,
  type Sex,
  type UpdateProfilePayload,
} from '@/services/profileService';

const router = useRouter();

interface FormState {
  birthdate: string;
  sex: Sex;
  height_cm: number | null;
  weight_kg: number | null;
  target_bmi: number | null;
  activity_level: ActivityLevel;
  goal_type: GoalType;
}

const form = reactive<FormState>({
  birthdate: '',
  sex: 'male',
  height_cm: null,
  weight_kg: null,
  target_bmi: null,
  activity_level: 'moderate',
  goal_type: 'maintain',
});

const errors = ref<Record<string, string[]>>({});
const generalError = ref('');
const loading = ref(true);
const submitting = ref(false);
const isExisting = ref(false);

const sexOptions: Array<{ value: Sex; label: string }> = [
  { value: 'male',   label: '男性' },
  { value: 'female', label: '女性' },
];

const activityOptions: Array<{ value: ActivityLevel; label: string }> = [
  { value: 'sedentary', label: '久坐（極少運動）' },
  { value: 'light',     label: '輕度活動（每週 1-3 次運動）' },
  { value: 'moderate',  label: '中度活動（每週 3-5 次運動）' },
  { value: 'active',    label: '高度活動（每週 6-7 次運動）' },
];

const goalOptions: Array<{ value: GoalType; label: string }> = [
  { value: 'lose_fat',    label: '減脂' },
  { value: 'gain_muscle', label: '增肌' },
  { value: 'maintain',    label: '維持' },
];

const today = new Date().toISOString().slice(0, 10); // 'YYYY-MM-DD'

onMounted(async () => {
  try {
    const profile = await profileService.getProfile();
    if (profile) {
      isExisting.value = true;
      if (profile.birthdate) form.birthdate = profile.birthdate;
      if (profile.sex) form.sex = profile.sex;
      form.height_cm  = profile.height_cm;
      form.weight_kg  = profile.weight_kg;
      form.target_bmi = profile.target_bmi;
      form.activity_level = profile.activity_level;
      form.goal_type      = profile.goal_type;
    }
  } catch {
    generalError.value = '無法取得個人資料，請稍後再試';
  } finally {
    loading.value = false;
  }
});

async function onSubmit(): Promise<void> {
  errors.value = {};
  generalError.value = '';

  if (
    form.height_cm === null ||
    form.weight_kg === null ||
    form.target_bmi === null ||
    !form.birthdate
  ) {
    generalError.value = '請填寫所有欄位';
    return;
  }

  submitting.value = true;
  try {
    const payload: UpdateProfilePayload = {
      birthdate:      form.birthdate,
      sex:            form.sex,
      height_cm:      form.height_cm,
      weight_kg:      form.weight_kg,
      target_bmi:     form.target_bmi,
      activity_level: form.activity_level,
      goal_type:      form.goal_type,
    };
    await profileService.updateProfile(payload);
    router.push({ name: 'dashboard' });
  } catch (e) {
    if (e instanceof AxiosError && e.response?.status === 422) {
      errors.value = e.response.data?.errors ?? {};
      generalError.value = e.response.data?.message ?? '輸入有誤';
    } else {
      generalError.value = '儲存失敗，請稍後再試';
    }
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="page">
    <div class="card">
      <header class="card-header">
        <h1>{{ isExisting ? '編輯個人資料' : '個人資料設定' }}</h1>
        <RouterLink to="/dashboard" class="back-link">← 回 Dashboard</RouterLink>
      </header>

      <p v-if="loading" class="loading">載入中…</p>

      <template v-else>
        <p v-if="generalError" class="alert">{{ generalError }}</p>

        <form @submit.prevent="onSubmit" novalidate>
          <div class="row">
            <div class="field">
              <label for="birthdate">生日</label>
              <input
                id="birthdate"
                v-model="form.birthdate"
                type="date"
                :max="today"
                :class="{ invalid: errors.birthdate }"
              />
              <small v-if="errors.birthdate" class="error">{{ errors.birthdate[0] }}</small>
            </div>

            <div class="field">
              <label for="sex">生理性別</label>
              <select
                id="sex"
                v-model="form.sex"
                :class="{ invalid: errors.sex }"
              >
                <option v-for="opt in sexOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
              <small v-if="errors.sex" class="error">{{ errors.sex[0] }}</small>
              <small v-else class="hint">用於 BMR 計算公式</small>
            </div>
          </div>

          <div class="field">
            <label for="height_cm">身高（公分）</label>
            <input
              id="height_cm"
              v-model.number="form.height_cm"
              type="number"
              step="0.1"
              min="50"
              max="300"
              placeholder="例：170"
              :class="{ invalid: errors.height_cm }"
            />
            <small v-if="errors.height_cm" class="error">{{ errors.height_cm[0] }}</small>
            <small v-else class="hint">合理範圍 50 ~ 300</small>
          </div>

          <div class="field">
            <label for="weight_kg">體重（公斤）</label>
            <input
              id="weight_kg"
              v-model.number="form.weight_kg"
              type="number"
              step="0.1"
              min="20"
              max="500"
              placeholder="例：65"
              :class="{ invalid: errors.weight_kg }"
            />
            <small v-if="errors.weight_kg" class="error">{{ errors.weight_kg[0] }}</small>
            <small v-else class="hint">合理範圍 20 ~ 500</small>
          </div>

          <div class="field">
            <label for="target_bmi">目標 BMI</label>
            <input
              id="target_bmi"
              v-model.number="form.target_bmi"
              type="number"
              step="0.1"
              min="10"
              max="50"
              placeholder="例：22"
              :class="{ invalid: errors.target_bmi }"
            />
            <small v-if="errors.target_bmi" class="error">{{ errors.target_bmi[0] }}</small>
            <small v-else class="hint">
              健康成人多在 18.5 ~ 24 之間。<br>
              ⚠️ BMI 僅作為一般參考，無法區分肌肉與脂肪。建議同時觀察體重趨勢、腰圍與體脂率。如果你的目標是增肌，BMI 上升不一定代表變差。
            </small>
          </div>

          <div class="field">
            <label for="activity_level">活動量</label>
            <select id="activity_level" v-model="form.activity_level" :class="{ invalid: errors.activity_level }">
              <option v-for="opt in activityOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <small v-if="errors.activity_level" class="error">{{ errors.activity_level[0] }}</small>
          </div>

          <div class="field">
            <label for="goal_type">目標類型</label>
            <select id="goal_type" v-model="form.goal_type" :class="{ invalid: errors.goal_type }">
              <option v-for="opt in goalOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <small v-if="errors.goal_type" class="error">{{ errors.goal_type[0] }}</small>
          </div>

          <div class="actions">
            <button type="submit" class="btn-primary" :disabled="submitting">
              {{ submitting ? '儲存中…' : '儲存' }}
            </button>
            <RouterLink to="/dashboard" class="btn-secondary">取消</RouterLink>
          </div>
        </form>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page { max-width: 560px; margin: 32px auto; padding: 0 24px; }
.card { padding: 32px; border: 1px solid #e2e8f0; border-radius: 12px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.card-header { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 24px; }
.card-header h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.back-link { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back-link:hover { color: #0ea5e9; }
.loading { text-align: center; color: #64748b; padding: 24px 0; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { margin-bottom: 16px; }
label { display: block; font-size: 0.875rem; color: #475569; margin-bottom: 6px; }
input, select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem; box-sizing: border-box; background: white; }
input:focus, select:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.15); }
input.invalid, select.invalid { border-color: #dc2626; }
.error { color: #dc2626; font-size: 0.8125rem; display: block; margin-top: 6px; }
.hint { color: #94a3b8; font-size: 0.8125rem; display: block; margin-top: 6px; }
.alert { background: #fef2f2; color: #b91c1c; padding: 10px 12px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem; }
.actions { display: flex; gap: 12px; margin-top: 24px; }
.btn-primary { flex: 1; background: #0ea5e9; color: white; border: 0; padding: 11px; border-radius: 8px; font-size: 1rem; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: #0284c7; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { display: inline-flex; align-items: center; justify-content: center; padding: 11px 20px; border: 1px solid #cbd5e1; border-radius: 8px; color: #475569; text-decoration: none; font-size: 1rem; }
.btn-secondary:hover { background: #f1f5f9; }
</style>

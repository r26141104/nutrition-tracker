<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { AxiosError } from 'axios';
import {
  foodService,
  CATEGORY_OPTIONS,
  type FoodCategory,
  type FoodPayload,
} from '@/services/foodService';
import { nutritionEstimateService } from '@/services/nutritionEstimateService';

const route = useRoute();
const router = useRouter();

interface FormState {
  name: string;
  brand: string;
  category: FoodCategory;
  serving_unit: string;
  serving_size: number | null;
  calories: number | null;
  protein_g: number | null;
  fat_g: number | null;
  carbs_g: number | null;
}

const form = reactive<FormState>({
  name: '',
  brand: '',
  category: 'rice_box',
  serving_unit: '份',
  serving_size: 1,
  calories: null,
  protein_g: null,
  fat_g: null,
  carbs_g: null,
});

const errors = ref<Record<string, string[]>>({});
const generalError = ref('');
const loading = ref(false);
const submitting = ref(false);
const estimating = ref(false);
const aiHint = ref('');
const aiError = ref('');

const editingId = computed<number | null>(() => {
  const id = route.params.id;
  if (!id || Array.isArray(id)) return null;
  const n = Number(id);
  return Number.isFinite(n) ? n : null;
});

const isEditing = computed(() => editingId.value !== null);

onMounted(async () => {
  if (!isEditing.value) return;
  loading.value = true;
  try {
    const food = await foodService.show(editingId.value!);
    if (!food.is_owned) {
      generalError.value = '只能編輯自己建立的食物，系統食物不可修改。';
      return;
    }
    form.name = food.name;
    form.brand = food.brand ?? '';
    form.category = food.category;
    form.serving_unit = food.serving_unit;
    form.serving_size = food.serving_size;
    form.calories = food.calories;
    form.protein_g = food.protein_g;
    form.fat_g = food.fat_g;
    form.carbs_g = food.carbs_g;
  } catch (e) {
    if (e instanceof AxiosError && e.response?.status === 404) {
      generalError.value = '找不到此食物，可能已被刪除或不屬於你。';
    } else {
      generalError.value = '載入食物資料失敗，請稍後再試。';
    }
  } finally {
    loading.value = false;
  }
});

async function onAIEstimate(): Promise<void> {
  const name = form.name.trim();
  if (name === '') {
    aiError.value = '請先輸入食物名稱';
    return;
  }
  aiError.value = '';
  aiHint.value = '';
  estimating.value = true;
  try {
    const est = await nutritionEstimateService.estimate(name);
    // 自動填入欄位（不覆蓋名稱，因為使用者剛輸入的）
    form.category = est.category as FoodCategory;
    form.serving_unit = est.serving_unit;
    form.serving_size = est.serving_size;
    form.calories = est.calories;
    form.protein_g = est.protein_g;
    form.fat_g = est.fat_g;
    form.carbs_g = est.carbs_g;
    aiHint.value = `🤖 AI 估算完成：${est.notes || '已自動填入欄位，可再調整後儲存。'}（誤差約 ±20%）`;
  } catch (e) {
    if (e instanceof AxiosError) {
      const status = e.response?.status;
      const msg = (e.response?.data as { message?: string })?.message;
      if (status === 422) {
        aiError.value = msg ?? '輸入有誤，請檢查食物名稱';
      } else if (status === 503) {
        aiError.value = msg ?? 'AI 服務暫時不可用，請稍後再試';
      } else {
        aiError.value = msg ?? 'AI 估算失敗，請稍後再試';
      }
    } else {
      aiError.value = 'AI 估算失敗，請稍後再試';
    }
  } finally {
    estimating.value = false;
  }
}

async function onSubmit(): Promise<void> {
  errors.value = {};
  generalError.value = '';

  // 數字欄位簡單檢查（後端會再做完整驗證）
  if (
    form.serving_size === null
    || form.calories === null
    || form.protein_g === null
    || form.fat_g === null
    || form.carbs_g === null
  ) {
    generalError.value = '請填寫所有數字欄位';
    return;
  }

  submitting.value = true;
  try {
    const payload: FoodPayload = {
      name: form.name.trim(),
      brand: form.brand.trim() === '' ? null : form.brand.trim(),
      category: form.category,
      serving_unit: form.serving_unit.trim(),
      serving_size: form.serving_size,
      calories: form.calories,
      protein_g: form.protein_g,
      fat_g: form.fat_g,
      carbs_g: form.carbs_g,
    };
    if (isEditing.value) {
      await foodService.update(editingId.value!, payload);
    } else {
      await foodService.create(payload);
    }
    router.push({ name: 'foods' });
  } catch (e) {
    if (e instanceof AxiosError) {
      if (e.response?.status === 422) {
        errors.value = e.response.data?.errors ?? {};
        generalError.value = e.response.data?.message ?? '輸入有誤';
      } else if (e.response?.status === 403) {
        generalError.value = e.response.data?.message ?? '您沒有權限修改此食物';
      } else {
        generalError.value = '儲存失敗，請稍後再試';
      }
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
        <h1>{{ isEditing ? '編輯食物' : '新增食物' }}</h1>
        <RouterLink to="/foods" class="back-link">← 回食物列表</RouterLink>
      </header>

      <p v-if="loading" class="loading">載入中…</p>

      <template v-else>
        <p v-if="generalError" class="alert">{{ generalError }}</p>

        <form @submit.prevent="onSubmit" novalidate>
          <div class="field">
            <label for="name">名稱<span class="req">*</span></label>
            <div class="name-row">
              <input
                id="name"
                v-model="form.name"
                type="text"
                maxlength="100"
                placeholder="例：自製雞肉沙拉、珍珠奶茶大杯、麥當勞大麥克"
                :class="{ invalid: errors.name }"
              />
              <button
                type="button"
                class="btn-ai"
                :disabled="estimating || form.name.trim() === ''"
                @click="onAIEstimate"
                title="用 AI 自動估算這個食物的熱量與營養"
              >
                {{ estimating ? '估算中…' : '🤖 AI 估算' }}
              </button>
            </div>
            <small v-if="errors.name" class="error">{{ errors.name[0] }}</small>
            <small v-if="aiError" class="error">{{ aiError }}</small>
            <small v-if="aiHint" class="ai-hint">{{ aiHint }}</small>
          </div>

          <div class="field">
            <label for="brand">品牌（可選）</label>
            <input
              id="brand"
              v-model="form.brand"
              type="text"
              maxlength="50"
              placeholder="例：自製、7-11、星巴克"
              :class="{ invalid: errors.brand }"
            />
            <small v-if="errors.brand" class="error">{{ errors.brand[0] }}</small>
          </div>

          <div class="field">
            <label for="category">類別<span class="req">*</span></label>
            <select
              id="category"
              v-model="form.category"
              :class="{ invalid: errors.category }"
            >
              <option v-for="opt in CATEGORY_OPTIONS" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <small v-if="errors.category" class="error">{{ errors.category[0] }}</small>
          </div>

          <div class="row-two">
            <div class="field">
              <label for="serving_unit">單位<span class="req">*</span></label>
              <input
                id="serving_unit"
                v-model="form.serving_unit"
                type="text"
                maxlength="20"
                placeholder="份 / 杯 / 顆 / g"
                :class="{ invalid: errors.serving_unit }"
              />
              <small v-if="errors.serving_unit" class="error">{{ errors.serving_unit[0] }}</small>
            </div>

            <div class="field">
              <label for="serving_size">份量數值<span class="req">*</span></label>
              <input
                id="serving_size"
                v-model.number="form.serving_size"
                type="number"
                step="0.01"
                min="0.01"
                :class="{ invalid: errors.serving_size }"
              />
              <small v-if="errors.serving_size" class="error">{{ errors.serving_size[0] }}</small>
            </div>
          </div>

          <div class="field">
            <label for="calories">熱量（kcal / 每份）<span class="req">*</span></label>
            <input
              id="calories"
              v-model.number="form.calories"
              type="number"
              step="1"
              min="0"
              max="99999"
              :class="{ invalid: errors.calories }"
            />
            <small v-if="errors.calories" class="error">{{ errors.calories[0] }}</small>
          </div>

          <div class="row-three">
            <div class="field">
              <label for="protein_g">蛋白質 (g)<span class="req">*</span></label>
              <input
                id="protein_g"
                v-model.number="form.protein_g"
                type="number"
                step="0.1"
                min="0"
                :class="{ invalid: errors.protein_g }"
              />
              <small v-if="errors.protein_g" class="error">{{ errors.protein_g[0] }}</small>
            </div>
            <div class="field">
              <label for="fat_g">脂肪 (g)<span class="req">*</span></label>
              <input
                id="fat_g"
                v-model.number="form.fat_g"
                type="number"
                step="0.1"
                min="0"
                :class="{ invalid: errors.fat_g }"
              />
              <small v-if="errors.fat_g" class="error">{{ errors.fat_g[0] }}</small>
            </div>
            <div class="field">
              <label for="carbs_g">碳水 (g)<span class="req">*</span></label>
              <input
                id="carbs_g"
                v-model.number="form.carbs_g"
                type="number"
                step="0.1"
                min="0"
                :class="{ invalid: errors.carbs_g }"
              />
              <small v-if="errors.carbs_g" class="error">{{ errors.carbs_g[0] }}</small>
            </div>
          </div>

          <div class="actions">
            <button type="submit" class="btn-primary" :disabled="submitting">
              {{ submitting ? '儲存中…' : (isEditing ? '更新' : '新增') }}
            </button>
            <RouterLink to="/foods" class="btn-secondary">取消</RouterLink>
          </div>
        </form>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page { max-width: 640px; margin: 32px auto; padding: 0 24px; }
.card { padding: 32px; border: 1px solid #e2e8f0; border-radius: 12px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.card-header { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
.card-header h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.back-link { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back-link:hover { color: #0ea5e9; }
.loading { text-align: center; color: #64748b; padding: 24px 0; }

.field { margin-bottom: 16px; }
.row-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.row-three { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }

label { display: block; font-size: 0.875rem; color: #475569; margin-bottom: 6px; }
.req { color: #dc2626; margin-left: 2px; }

input, select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem; box-sizing: border-box; background: white; }
input:focus, select:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.15); }
input.invalid, select.invalid { border-color: #dc2626; }

.error { color: #dc2626; font-size: 0.8125rem; display: block; margin-top: 6px; }
.alert { background: #fef2f2; color: #b91c1c; padding: 10px 12px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem; }

.actions { display: flex; gap: 12px; margin-top: 24px; }
.btn-primary { flex: 1; background: #0ea5e9; color: white; border: 0; padding: 11px; border-radius: 8px; font-size: 1rem; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: #0284c7; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { display: inline-flex; align-items: center; justify-content: center; padding: 11px 20px; border: 1px solid #cbd5e1; border-radius: 8px; color: #475569; text-decoration: none; font-size: 1rem; }
.btn-secondary:hover { background: #f1f5f9; }

.name-row { display: flex; gap: 8px; align-items: stretch; }
.name-row input { flex: 1; }
.btn-ai {
  white-space: nowrap;
  background: #ede9fe;
  color: #6d28d9;
  border: 1px solid #c4b5fd;
  border-radius: 8px;
  padding: 0 14px;
  font-size: 0.875rem;
  cursor: pointer;
  font-weight: 500;
}
.btn-ai:hover:not(:disabled) { background: #ddd6fe; }
.btn-ai:disabled { opacity: 0.5; cursor: not-allowed; }
.ai-hint {
  display: block;
  margin-top: 8px;
  padding: 8px 10px;
  background: #f5f3ff;
  border: 1px solid #ddd6fe;
  border-radius: 6px;
  color: #6d28d9;
  font-size: 0.8125rem;
  line-height: 1.5;
}
</style>

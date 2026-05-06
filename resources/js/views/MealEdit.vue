<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { AxiosError } from 'axios';
import {
  mealService,
  MEAL_TYPE_OPTIONS,
  type MealType,
  type Meal,
  type MealPayload,
} from '@/services/mealService';
import { foodService, type Food } from '@/services/foodService';
import { nutritionEstimateService } from '@/services/nutritionEstimateService';
import { ElMessage } from 'element-plus';

const route = useRoute();
const router = useRouter();

interface DraftItem {
  // 新增 / 已存在於資料庫的 item，編輯時都用這個結構
  client_id: number;        // local-only，給 v-for key 用
  food: Food | null;        // 顯示用（name / serving）
  food_id: number;
  quantity: number;
  // 為了即時顯示 totals，記住 snapshot 來源（用當下 Food）
  per_unit_calories: number;
  per_unit_protein_g: number;
  per_unit_fat_g: number;
  per_unit_carbs_g: number;
}

let nextClientId = 1;

const editingId = computed<number | null>(() => {
  const id = route.params.id;
  if (!id || Array.isArray(id)) return null;
  const n = Number(id);
  return Number.isFinite(n) ? n : null;
});
const isEditing = computed(() => editingId.value !== null);

// === 表單狀態 ===
const form = reactive({
  meal_type: (route.query.meal_type as MealType) || 'lunch' as MealType,
  // datetime-local 格式 'YYYY-MM-DDTHH:mm'
  eaten_at_local: defaultEatenAt(),
  note: '',
});

const items = ref<DraftItem[]>([]);
let itemsDirty = false; // 編輯模式：使用者沒動 items 就不重送（避免重 snapshot）

const loading = ref(false);
const submitting = ref(false);
const errors = ref<Record<string, string[]>>({});
const generalError = ref('');

// === 食物搜尋 ===
const searchInput = ref('');
const searchResults = ref<Food[]>([]);
const searching = ref(false);
const showResults = ref(false);
// 搜尋分頁狀態
const searchPage = ref<number>(1);
const searchHasMore = ref<boolean>(false);
const SEARCH_PER_PAGE = 15;

// 下方「準備加入」的食物 + 份量
const pickedFood = ref<Food | null>(null);
const pickedQty = ref<number>(1);

// AI 估算狀態
const aiEstimating = ref(false);
const aiError = ref('');
const aiHint = ref('');

let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchInput, () => {
  if (searchTimer) clearTimeout(searchTimer);
  if (!searchInput.value.trim()) {
    searchResults.value = [];
    showResults.value = false;
    return;
  }
  searchTimer = setTimeout(doSearch, 300);
});

async function doSearch(append = false): Promise<void> {
  searching.value = true;
  try {
    const targetPage = append ? searchPage.value + 1 : 1;
    const res = await foodService.list({
      search: searchInput.value.trim(),
      page: targetPage,
      per_page: SEARCH_PER_PAGE,
    });
    if (append) {
      searchResults.value = [...searchResults.value, ...res.data];
    } else {
      searchResults.value = res.data;
    }
    searchPage.value = targetPage;
    searchHasMore.value = res.meta.last_page > targetPage;
    showResults.value = true;
  } catch {
    if (!append) searchResults.value = [];
  } finally {
    searching.value = false;
  }
}

async function loadMoreSearch(): Promise<void> {
  if (searching.value || !searchHasMore.value) return;
  await doSearch(true);
}

function pickFood(food: Food): void {
  pickedFood.value = food;
  pickedQty.value = 1;
  searchInput.value = '';
  searchResults.value = [];
  showResults.value = false;
  aiHint.value = '';
  aiError.value = '';
}

/**
 * AI 估算並建立食物，建立成功後直接 pick 起來，使用者只要設份量按「加入」就能下到項目
 */
async function onAIEstimateAndAdd(): Promise<void> {
  const name = searchInput.value.trim();
  if (name === '') {
    aiError.value = '請先輸入食物名稱';
    return;
  }
  aiError.value = '';
  aiHint.value = '';
  aiEstimating.value = true;
  try {
    const res = await nutritionEstimateService.estimateAndCreate(name);
    // 直接 pick 起來，使用者改份量按「加入」即可
    pickedFood.value = res.food;
    pickedQty.value = 1;
    searchInput.value = '';
    searchResults.value = [];
    showResults.value = false;
    aiHint.value = `🤖 AI 已估算並建立「${res.food.name}」（誤差約 ±20%，標記為低可信度）。設定份量後按「加入」。`;
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
    aiEstimating.value = false;
  }
}

function cancelPick(): void {
  pickedFood.value = null;
  pickedQty.value = 1;
}

/**
 * 套用「常見份量」預設按鈕：把 grams 換算成 quantity。
 * 假設食物的 serving_size 是以 g 為單位（FoodResource 預設行為）。
 */
function applyPreset(grams: number): void {
  if (!pickedFood.value) return;
  const food = pickedFood.value;
  if (food.serving_size && food.serving_size > 0) {
    pickedQty.value = Math.round((grams / food.serving_size) * 100) / 100;
  } else {
    pickedQty.value = 1;
  }
}

function addPickedItem(): void {
  if (!pickedFood.value) return;
  if (!pickedQty.value || pickedQty.value <= 0) {
    ElMessage.warning('份量必須大於 0');
    return;
  }

  items.value.push({
    client_id:           nextClientId++,
    food:                pickedFood.value,
    food_id:             pickedFood.value.id,
    quantity:            pickedQty.value,
    per_unit_calories:   pickedFood.value.calories,
    per_unit_protein_g:  pickedFood.value.protein_g,
    per_unit_fat_g:      pickedFood.value.fat_g,
    per_unit_carbs_g:    pickedFood.value.carbs_g,
  });
  itemsDirty = true;
  cancelPick();
}

function removeItem(clientId: number): void {
  items.value = items.value.filter((i) => i.client_id !== clientId);
  itemsDirty = true;
}

function changeQty(item: DraftItem, qty: number): void {
  item.quantity = qty;
  itemsDirty = true;
}

// === 即時計算 totals ===
const computedTotals = computed(() => {
  let cal = 0, p = 0, f = 0, c = 0;
  for (const i of items.value) {
    const q = Number(i.quantity) || 0;
    cal += i.per_unit_calories * q;
    p += i.per_unit_protein_g * q;
    f += i.per_unit_fat_g * q;
    c += i.per_unit_carbs_g * q;
  }
  return {
    calories: Math.round(cal),
    protein_g: Math.round(p * 100) / 100,
    fat_g: Math.round(f * 100) / 100,
    carbs_g: Math.round(c * 100) / 100,
  };
});

// === 載入既有 meal（編輯模式） ===
onMounted(async () => {
  if (!isEditing.value) {
    // 新增模式，看看有沒有從 query 帶日期過來
    const dateQ = route.query.date as string | undefined;
    if (dateQ) {
      // 把日期套進 eaten_at_local（時間用現在，整點）
      const now = new Date();
      const hh = String(now.getHours()).padStart(2, '0');
      const mm = String(now.getMinutes()).padStart(2, '0');
      form.eaten_at_local = `${dateQ}T${hh}:${mm}`;
    }

    // 階段 I：從附近店家 / 連鎖菜單跳進來的兩種 query
    // (a) prefill_food_id：直接把這個 food picked 起來（連鎖店菜單的「加入」按鈕）
    const prefillId = Number(route.query.prefill_food_id);
    if (Number.isFinite(prefillId) && prefillId > 0) {
      try {
        const food = await foodService.show(prefillId);
        pickedFood.value = food;
        pickedQty.value = 1;
      } catch {
        // 食物不存在就靜默跳過
      }
    }
    // (b) ai_food_name：搜尋框預填食物名（讓使用者按 AI 估算）
    const aiName = route.query.ai_food_name as string | undefined;
    if (aiName) {
      searchInput.value = aiName;
    }
    return;
  }

  loading.value = true;
  try {
    const meal = await mealService.show(editingId.value!);
    form.meal_type = meal.meal_type;
    form.eaten_at_local = isoToLocalInput(meal.eaten_at);
    form.note = meal.note ?? '';

    items.value = meal.items.map<DraftItem>((it) => ({
      client_id:           nextClientId++,
      food:                it.food_summary
        ? ({
            id: it.food_summary.id,
            name: it.food_summary.name,
            brand: it.food_summary.brand,
            category: it.food_summary.category as Food['category'],
            serving_unit: it.food_summary.serving_unit,
            serving_size: it.food_summary.serving_size,
            calories: it.snapshot.calories,
            protein_g: it.snapshot.protein_g,
            fat_g: it.snapshot.fat_g,
            carbs_g: it.snapshot.carbs_g,
            is_system: false,
            created_by_user_id: null,
            is_owned: false,
            created_at: null,
            updated_at: null,
          })
        : null,
      food_id:             it.food_id ?? 0,
      quantity:            it.quantity,
      per_unit_calories:   it.snapshot.calories,
      per_unit_protein_g:  it.snapshot.protein_g,
      per_unit_fat_g:      it.snapshot.fat_g,
      per_unit_carbs_g:    it.snapshot.carbs_g,
    }));
    itemsDirty = false; // 載入後預設「沒動過」
  } catch (e) {
    if (e instanceof AxiosError && e.response?.status === 404) {
      generalError.value = '找不到此餐點，可能已被刪除';
    } else {
      generalError.value = '載入餐點失敗，請稍後再試';
    }
  } finally {
    loading.value = false;
  }
});

// === 送出 ===
async function onSubmit(): Promise<void> {
  errors.value = {};
  generalError.value = '';

  // 新增時若沒加任何食物 → 阻擋並提示
  if (!isEditing.value && items.value.filter((i) => i.food_id > 0).length === 0) {
    ElMessage.warning('請至少加入一項食物');
    generalError.value = '請至少加入一項食物';
    return;
  }

  // 把 datetime-local 轉成後端可接受的格式 'YYYY-MM-DD HH:mm:ss'
  const eatenAt = localInputToBackend(form.eaten_at_local);
  if (!eatenAt) {
    generalError.value = '請填寫合法的用餐時間';
    return;
  }

  const payload: MealPayload = {
    meal_type: form.meal_type,
    eaten_at:  eatenAt,
    note:      form.note.trim() === '' ? null : form.note.trim(),
  };

  // 編輯時：只有 items 真的被動過，才送 items（避免重 snapshot）
  // 新增時：永遠送 items（即使是空陣列）
  if (!isEditing.value) {
    payload.items = items.value
      .filter((i) => i.food_id > 0)
      .map((i) => ({ food_id: i.food_id, quantity: Number(i.quantity) }));
  } else if (itemsDirty) {
    payload.items = items.value
      .filter((i) => i.food_id > 0)
      .map((i) => ({ food_id: i.food_id, quantity: Number(i.quantity) }));
  }

  submitting.value = true;
  try {
    let saved: Meal;
    if (isEditing.value) {
      saved = await mealService.update(editingId.value!, payload);
    } else {
      saved = await mealService.create(payload);
    }
    router.push({ name: 'meals', query: { date: saved.eaten_at.slice(0, 10) } });
  } catch (e) {
    if (e instanceof AxiosError) {
      if (e.response?.status === 422) {
        errors.value = e.response.data?.errors ?? {};
        generalError.value = e.response.data?.message ?? '輸入有誤';
      } else if (e.response?.status === 403) {
        generalError.value = e.response.data?.message ?? '您沒有權限修改此餐點';
      } else if (e.response?.status === 404) {
        generalError.value = '找不到此餐點';
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

// === 工具 ===
function defaultEatenAt(): string {
  const now = new Date();
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
}

function pad(n: number): string {
  return String(n).padStart(2, '0');
}

function isoToLocalInput(iso: string): string {
  const d = new Date(iso);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function localInputToBackend(local: string): string | null {
  if (!local) return null;
  // local 格式 'YYYY-MM-DDTHH:mm'，補成 'YYYY-MM-DD HH:mm:00'
  const m = local.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})$/);
  if (!m) return null;
  return `${m[1]} ${m[2]}:00`;
}
</script>

<template>
  <div class="page">
    <div class="card">
      <header class="card-header">
        <h1>{{ isEditing ? '編輯餐點' : '新增餐點' }}</h1>
        <RouterLink to="/meals" class="back-link">← 回飲食紀錄</RouterLink>
      </header>

      <p v-if="loading" class="loading">載入中…</p>

      <template v-else>
        <p v-if="generalError" class="alert">{{ generalError }}</p>

        <form @submit.prevent="onSubmit" novalidate>
          <!-- 基本資料 -->
          <div class="row-two">
            <div class="field">
              <label for="meal_type">餐別<span class="req">*</span></label>
              <select
                id="meal_type"
                v-model="form.meal_type"
                :class="{ invalid: errors.meal_type }"
              >
                <option v-for="opt in MEAL_TYPE_OPTIONS" :key="opt.value" :value="opt.value">
                  {{ opt.icon }} {{ opt.label }}
                </option>
              </select>
              <small v-if="errors.meal_type" class="error">{{ errors.meal_type[0] }}</small>
            </div>

            <div class="field">
              <label for="eaten_at">用餐時間<span class="req">*</span></label>
              <input
                id="eaten_at"
                v-model="form.eaten_at_local"
                type="datetime-local"
                :class="{ invalid: errors.eaten_at }"
              />
              <small v-if="errors.eaten_at" class="error">{{ errors.eaten_at[0] }}</small>
            </div>
          </div>

          <div class="field">
            <label for="note">備註（可選）</label>
            <textarea
              id="note"
              v-model="form.note"
              maxlength="500"
              rows="2"
              placeholder="例：和同事在 7-11"
              :class="{ invalid: errors.note }"
            ></textarea>
            <small v-if="errors.note" class="error">{{ errors.note[0] }}</small>
          </div>

          <!-- 項目區 -->
          <h2 class="section-title">本餐項目</h2>

          <ul v-if="items.length > 0" class="items">
            <li v-for="item in items" :key="item.client_id" class="item-row">
              <div class="item-info">
                <span class="item-name">
                  {{ item.food?.name ?? '（已刪除的食物）' }}
                </span>
                <small v-if="item.food?.brand" class="item-brand">{{ item.food.brand }}</small>
              </div>
              <input
                type="number"
                step="0.01"
                min="0.01"
                class="qty-input"
                :value="item.quantity"
                @input="(e) => changeQty(item, Number((e.target as HTMLInputElement).value))"
              />
              <span class="item-unit">{{ item.food?.serving_unit ?? '份' }}</span>
              <span class="item-cal">{{ Math.round(item.per_unit_calories * Number(item.quantity)) }} kcal</span>
              <button
                type="button"
                class="btn-remove"
                @click="removeItem(item.client_id)"
              >
                ✕
              </button>
            </li>
          </ul>
          <p v-else class="empty-items">尚未加入任何食物。下方搜尋來加入。</p>

          <!-- 食物搜尋 + 份量 -->
          <div class="picker">
            <div class="picker-search">
              <input
                v-model="searchInput"
                type="text"
                placeholder="搜尋食物（如：便當、星巴克、雞肉）"
                class="search-input"
                @focus="showResults = searchResults.length > 0"
              />
              <ul
                v-if="showResults && searchResults.length > 0"
                class="search-results"
              >
                <li v-for="f in searchResults" :key="f.id" @click="pickFood(f)">
                  <span class="result-name">{{ f.name }}</span>
                  <small v-if="f.brand">· {{ f.brand }}</small>
                  <span class="result-cal">{{ f.calories }} kcal / {{ f.serving_size }} {{ f.serving_unit }}</span>
                </li>
                <li
                  v-if="searchHasMore"
                  class="load-more"
                  @click.stop="loadMoreSearch"
                >
                  {{ searching ? '載入中…' : '⌄ 載入更多' }}
                </li>
              </ul>
              <p v-else-if="searching" class="search-hint">搜尋中…</p>
              <div v-else-if="searchInput && !searching && searchResults.length === 0" class="ai-fallback">
                <p class="search-hint">
                  資料庫中找不到「<strong>{{ searchInput }}</strong>」 ─
                </p>
                <div class="ai-fallback-actions">
                  <button
                    type="button"
                    class="btn-ai"
                    :disabled="aiEstimating"
                    @click="onAIEstimateAndAdd"
                    title="用 AI 自動估算並加入資料庫"
                  >
                    {{ aiEstimating ? 'AI 估算中…' : '🤖 用 AI 估算並加入' }}
                  </button>
                  <RouterLink to="/foods/new" class="ai-fallback-link">或手動新增</RouterLink>
                </div>
                <p v-if="aiError" class="error">{{ aiError }}</p>
              </div>
              <p v-if="aiHint" class="ai-hint">{{ aiHint }}</p>
            </div>

            <!-- 已選食物，輸入份量 -->
            <div v-if="pickedFood" class="picked">
              <div class="picked-info">
                <strong>{{ pickedFood.name }}</strong>
                <small v-if="pickedFood.brand">{{ pickedFood.brand }}</small>
                <span class="picked-meta">
                  每 {{ pickedFood.serving_size }} {{ pickedFood.serving_unit }} ·
                  {{ pickedFood.calories }} kcal
                </span>
              </div>
              <input
                v-model.number="pickedQty"
                type="number"
                step="0.01"
                min="0.01"
                class="qty-input"
              />
              <span class="picked-unit">{{ pickedFood.serving_unit }}</span>
              <button type="button" class="btn-add" @click="addPickedItem">加入</button>
              <button type="button" class="btn-cancel" @click="cancelPick">×</button>
            </div>

            <!-- 常見份量快速按鈕（後端 CommonServingService 提供） -->
            <div
              v-if="pickedFood && pickedFood.serving_presets && pickedFood.serving_presets.length > 0"
              class="serving-presets"
            >
              <span class="preset-label">常見份量：</span>
              <button
                v-for="(preset, idx) in pickedFood.serving_presets"
                :key="idx"
                type="button"
                class="preset-btn"
                @click="applyPreset(preset.grams)"
              >
                {{ preset.label }}
              </button>
            </div>
          </div>

          <!-- 即時 totals -->
          <div class="live-totals">
            <span class="live-cal">{{ computedTotals.calories }} kcal</span>
            <span class="live-macros">
              P {{ computedTotals.protein_g }} ·
              F {{ computedTotals.fat_g }} ·
              C {{ computedTotals.carbs_g }}
            </span>
          </div>

          <!-- 動作 -->
          <div class="actions">
            <button type="submit" class="btn-primary" :disabled="submitting">
              {{ submitting ? '儲存中…' : (isEditing ? '更新' : '新增') }}
            </button>
            <RouterLink to="/meals" class="btn-secondary">取消</RouterLink>
          </div>
        </form>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page { max-width: 720px; margin: 32px auto; padding: 0 24px 64px; }
.card { padding: 28px; border: 1px solid #e2e8f0; border-radius: 12px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.card-header { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 20px; gap: 16px; flex-wrap: wrap; }
.card-header h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.back-link { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back-link:hover { color: #0ea5e9; }
.loading { text-align: center; color: #64748b; padding: 24px 0; }

.field { margin-bottom: 16px; }
.row-two { display: grid; grid-template-columns: 1fr 1.5fr; gap: 12px; }
@media (max-width: 480px) { .row-two { grid-template-columns: 1fr; } }

label { display: block; font-size: 0.875rem; color: #475569; margin-bottom: 6px; }
.req { color: #dc2626; margin-left: 2px; }
input, select, textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem; box-sizing: border-box; background: white; font-family: inherit; }
input:focus, select:focus, textarea:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.15); }
input.invalid, select.invalid, textarea.invalid { border-color: #dc2626; }
textarea { resize: vertical; }
.error { color: #dc2626; font-size: 0.8125rem; display: block; margin-top: 6px; }
.alert { background: #fef2f2; color: #b91c1c; padding: 10px 12px; border-radius: 8px; margin-bottom: 16px; font-size: 0.875rem; }

.section-title { margin: 24px 0 12px; font-size: 1rem; color: #334155; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }

.items { list-style: none; margin: 0 0 12px; padding: 0; }
.item-row { display: grid; grid-template-columns: 1fr 90px auto 80px 32px; gap: 8px; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.item-info { display: flex; flex-direction: column; min-width: 0; }
.item-name { font-size: 0.9375rem; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.item-brand { font-size: 0.75rem; color: #94a3b8; }
.qty-input { padding: 6px 8px; font-size: 0.9375rem; text-align: right; font-variant-numeric: tabular-nums; }
.item-unit { font-size: 0.8125rem; color: #64748b; }
.item-cal { font-size: 0.875rem; color: #0ea5e9; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; }
.btn-remove { width: 28px; height: 28px; border: 0; background: transparent; color: #dc2626; cursor: pointer; border-radius: 6px; font-size: 1rem; line-height: 1; }
.btn-remove:hover { background: #fef2f2; }

.empty-items { color: #94a3b8; font-size: 0.875rem; padding: 12px 0; text-align: center; font-style: italic; }

.picker { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 14px; margin-top: 12px; position: relative; }
.picker-search { position: relative; }
.search-input { background: white; }
.search-results { position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: white; border: 1px solid #cbd5e1; border-radius: 8px; max-height: 280px; overflow-y: auto; list-style: none; margin: 0; padding: 4px 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); z-index: 10; }
.search-results li { padding: 8px 12px; cursor: pointer; display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
.search-results li:hover { background: #f0f9ff; }
.search-results li.load-more {
  text-align: center;
  font-size: 0.8125rem;
  color: #6366f1;
  font-weight: 500;
  border-top: 1px solid #e2e8f0;
}
.search-results li.load-more:hover { background: #eef2ff; }
.result-name { font-size: 0.9375rem; color: #0f172a; }
.result-cal { margin-left: auto; font-size: 0.8125rem; color: #0ea5e9; font-weight: 500; }
.search-hint { margin: 8px 0 0; font-size: 0.8125rem; color: #64748b; }
.search-hint a { color: #0ea5e9; text-decoration: none; }

.picked { display: grid; grid-template-columns: 1fr 90px auto auto auto; gap: 8px; align-items: center; margin-top: 12px; padding: 10px; background: white; border: 1px solid #bae6fd; border-radius: 8px; }
.picked-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.picked-info strong { font-size: 0.9375rem; color: #0f172a; }
.picked-info small { font-size: 0.75rem; color: #64748b; }
.picked-meta { font-size: 0.75rem; color: #94a3b8; }
.picked-unit { font-size: 0.8125rem; color: #64748b; }
.btn-add { background: #0ea5e9; color: white; border: 0; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 0.875rem; font-weight: 500; }
.btn-add:hover { background: #0284c7; }
.btn-cancel { background: white; color: #64748b; border: 1px solid #cbd5e1; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.875rem; }
.btn-cancel:hover { background: #f1f5f9; }

/* 常見份量快速按鈕 */
.serving-presets { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 10px; padding: 8px 10px; background: #fffbeb; border: 1px dashed #fcd34d; border-radius: 8px; }
.preset-label { font-size: 0.8125rem; color: #92400e; font-weight: 500; }
.preset-btn { background: white; color: #92400e; border: 1px solid #fcd34d; padding: 4px 10px; border-radius: 999px; font-size: 0.8125rem; cursor: pointer; transition: all 0.15s; }
.preset-btn:hover { background: #fef3c7; border-color: #f59e0b; }

.live-totals { display: flex; justify-content: space-between; align-items: baseline; padding: 14px 16px; background: #f0f9ff; border-radius: 8px; margin: 16px 0 20px; }
.live-cal { font-size: 1.5rem; font-weight: 700; color: #0ea5e9; }
.live-macros { font-size: 0.875rem; color: #475569; }

.actions { display: flex; gap: 12px; margin-top: 8px; }
.btn-primary { flex: 1; background: #0ea5e9; color: white; border: 0; padding: 11px; border-radius: 8px; font-size: 1rem; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: #0284c7; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { display: inline-flex; align-items: center; justify-content: center; padding: 11px 20px; border: 1px solid #cbd5e1; border-radius: 8px; color: #475569; text-decoration: none; font-size: 1rem; }
.btn-secondary:hover { background: #f1f5f9; }

.ai-fallback { margin-top: 8px; padding: 10px 12px; background: white; border: 1px solid #ddd6fe; border-radius: 8px; }
.ai-fallback .search-hint { margin: 0 0 8px; }
.ai-fallback-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.ai-fallback-link { font-size: 0.8125rem; color: #64748b; text-decoration: none; }
.ai-fallback-link:hover { color: #0ea5e9; }
.btn-ai {
  white-space: nowrap;
  background: #ede9fe;
  color: #6d28d9;
  border: 1px solid #c4b5fd;
  border-radius: 8px;
  padding: 8px 14px;
  font-size: 0.875rem;
  cursor: pointer;
  font-weight: 500;
}
.btn-ai:hover:not(:disabled) { background: #ddd6fe; }
.btn-ai:disabled { opacity: 0.5; cursor: not-allowed; }
.ai-hint {
  margin-top: 10px;
  padding: 8px 10px;
  background: #f5f3ff;
  border: 1px solid #ddd6fe;
  border-radius: 6px;
  color: #6d28d9;
  font-size: 0.8125rem;
  line-height: 1.5;
}
</style>

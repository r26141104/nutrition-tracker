<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import {
  mealService,
  MEAL_TYPE_OPTIONS,
  MEAL_TYPE_LABEL,
  MEAL_TYPE_ICON,
  type Meal,
  type MealType,
  type DailySummary,
} from '@/services/mealService';

const router = useRouter();

const today = new Date().toISOString().slice(0, 10);
const selectedDate = ref<string>(today);

const meals = ref<Meal[]>([]);
const summary = ref<DailySummary | null>(null);
const loading = ref(false);
const errorMsg = ref('');

onMounted(() => {
  fetchAll();
});

watch(selectedDate, () => {
  fetchAll();
});

async function fetchAll(): Promise<void> {
  loading.value = true;
  errorMsg.value = '';
  try {
    const [mealsRes, summaryRes] = await Promise.all([
      mealService.list({ date: selectedDate.value }),
      mealService.dailySummary(selectedDate.value),
    ]);
    meals.value = mealsRes;
    summary.value = summaryRes;
  } catch {
    errorMsg.value = '載入飲食紀錄失敗，請稍後再試';
    meals.value = [];
    summary.value = null;
  } finally {
    loading.value = false;
  }
}

// 把 meals 按 meal_type 分桶（順序按 MEAL_TYPE_OPTIONS）
const mealsByType = computed<Record<MealType, Meal[]>>(() => {
  const buckets: Record<MealType, Meal[]> = {
    breakfast: [],
    lunch:     [],
    dinner:    [],
    snack:     [],
  };
  for (const m of meals.value) {
    buckets[m.meal_type].push(m);
  }
  // 每個 bucket 內按 eaten_at 升冪
  for (const k of Object.keys(buckets) as MealType[]) {
    buckets[k].sort((a, b) => a.eaten_at.localeCompare(b.eaten_at));
  }
  return buckets;
});

function formatTime(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit', hour12: false });
}

function shiftDay(delta: number): void {
  const d = new Date(selectedDate.value);
  d.setDate(d.getDate() + delta);
  selectedDate.value = d.toISOString().slice(0, 10);
}

function goToday(): void {
  selectedDate.value = today;
}

function goNew(presetType?: MealType): void {
  router.push({
    name: 'meal-new',
    query: presetType
      ? { meal_type: presetType, date: selectedDate.value }
      : { date: selectedDate.value },
  });
}

function goEdit(meal: Meal): void {
  router.push({ name: 'meal-edit', params: { id: meal.id } });
}

async function onDelete(meal: Meal): Promise<void> {
  const ok = window.confirm(
    `確定要刪除這筆 ${MEAL_TYPE_LABEL[meal.meal_type]}（${formatTime(meal.eaten_at)}）嗎？`,
  );
  if (!ok) return;

  try {
    await mealService.delete(meal.id);
    await fetchAll();
  } catch {
    window.alert('刪除失敗，請稍後再試');
  }
}

const isToday = computed(() => selectedDate.value === today);
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div class="topbar-left">
        <RouterLink to="/dashboard" class="back-link">← Dashboard</RouterLink>
        <h1>飲食紀錄</h1>
      </div>
      <button type="button" class="btn-primary" @click="goNew()">+ 新增餐點</button>
    </header>

    <!-- 日期切換 -->
    <div class="date-bar">
      <button type="button" class="btn-day" @click="shiftDay(-1)">←</button>
      <input v-model="selectedDate" type="date" class="date-input" />
      <button type="button" class="btn-day" @click="shiftDay(1)">→</button>
      <button
        type="button"
        class="btn-today"
        :disabled="isToday"
        @click="goToday"
      >
        今天
      </button>
    </div>

    <p v-if="loading" class="state">載入中…</p>
    <p v-else-if="errorMsg" class="state error">{{ errorMsg }}</p>

    <template v-else>
      <!-- 今日合計（沒目標時也能看自己吃了多少） -->
      <section v-if="summary" class="daily-summary">
        <h2 class="daily-title">{{ selectedDate }} 總計</h2>
        <div class="daily-stats">
          <div class="stat stat-cal">
            <span class="stat-num">{{ summary.totals.calories }}</span>
            <span class="stat-unit">kcal</span>
          </div>
          <div class="stat-macros">
            <div><dt>蛋白質</dt><dd>{{ summary.totals.protein_g }} g</dd></div>
            <div><dt>脂肪</dt><dd>{{ summary.totals.fat_g }} g</dd></div>
            <div><dt>碳水</dt><dd>{{ summary.totals.carbs_g }} g</dd></div>
          </div>
        </div>
        <p class="daily-meta">
          共 <strong>{{ summary.meal_count }}</strong> 筆餐點
        </p>
      </section>

      <!-- 4 個餐別分區 -->
      <section
        v-for="opt in MEAL_TYPE_OPTIONS"
        :key="opt.value"
        class="bucket"
      >
        <header class="bucket-head">
          <h2 class="bucket-title">
            <span class="bucket-icon">{{ opt.icon }}</span>
            <span>{{ opt.label }}</span>
            <span class="bucket-count">
              {{ mealsByType[opt.value].length }} 筆
            </span>
          </h2>
          <button type="button" class="btn-add-bucket" @click="goNew(opt.value)">
            + 新增
          </button>
        </header>

        <div v-if="mealsByType[opt.value].length === 0" class="bucket-empty">
          還沒有 {{ opt.label }} 的紀錄
        </div>

        <div v-else class="meal-list">
          <article
            v-for="meal in mealsByType[opt.value]"
            :key="meal.id"
            class="meal-card"
          >
            <header class="meal-head">
              <span class="meal-time">{{ formatTime(meal.eaten_at) }}</span>
              <span class="meal-totals">
                {{ meal.totals.calories }} kcal
                <small>· P {{ meal.totals.protein_g }} / F {{ meal.totals.fat_g }} / C {{ meal.totals.carbs_g }}</small>
              </span>
            </header>

            <ul v-if="meal.items.length > 0" class="item-list">
              <li v-for="item in meal.items" :key="item.id">
                <span class="item-name">
                  {{ item.food_summary?.name ?? '（已刪除的食物）' }}
                </span>
                <span class="item-qty">× {{ item.quantity }}</span>
                <span class="item-cal">{{ item.total.calories }} kcal</span>
              </li>
            </ul>
            <p v-else class="meal-empty-items">（尚未加入食物）</p>

            <p v-if="meal.note" class="meal-note">📝 {{ meal.note }}</p>

            <footer class="meal-actions">
              <button type="button" class="btn-small" @click="goEdit(meal)">編輯</button>
              <button type="button" class="btn-small btn-danger" @click="onDelete(meal)">
                刪除
              </button>
            </footer>
          </article>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.page { max-width: 720px; margin: 32px auto; padding: 0 24px 64px; }

.topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px; gap: 16px; flex-wrap: wrap; }
.topbar-left { display: flex; align-items: baseline; gap: 16px; }
.topbar h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.back-link { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back-link:hover { color: #0ea5e9; }
.btn-primary { background: #0ea5e9; color: white; border: 0; padding: 8px 16px; border-radius: 8px; font-size: 0.9375rem; font-weight: 500; cursor: pointer; }
.btn-primary:hover { background: #0284c7; }

.date-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
.btn-day { width: 36px; height: 36px; background: white; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; color: #475569; font-size: 1rem; }
.btn-day:hover { background: #f1f5f9; }
.date-input { flex: 1; max-width: 200px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9375rem; }
.btn-today { background: white; border: 1px solid #cbd5e1; padding: 8px 14px; border-radius: 8px; cursor: pointer; color: #475569; font-size: 0.875rem; }
.btn-today:hover:not(:disabled) { background: #f1f5f9; }
.btn-today:disabled { opacity: 0.4; cursor: not-allowed; }

.state { text-align: center; padding: 40px 16px; color: #64748b; font-size: 0.9375rem; }
.state.error { color: #dc2626; }

/* 今日合計 */
.daily-summary { background: linear-gradient(135deg, #f0f9ff, #ecfeff); border: 1px solid #bae6fd; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; }
.daily-title { margin: 0 0 8px; font-size: 0.875rem; color: #0369a1; font-weight: 600; }
.daily-stats { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.stat-cal { display: flex; align-items: baseline; gap: 4px; }
.stat-num { font-size: 2rem; font-weight: 700; color: #0ea5e9; }
.stat-unit { color: #64748b; font-size: 0.9375rem; }
.stat-macros { display: flex; gap: 16px; }
.stat-macros > div { text-align: center; }
.stat-macros dt { font-size: 0.6875rem; color: #64748b; margin: 0 0 2px; }
.stat-macros dd { margin: 0; font-size: 0.9375rem; font-weight: 600; color: #0f172a; }
.daily-meta { margin: 8px 0 0; font-size: 0.8125rem; color: #64748b; }

/* 餐別分區 */
.bucket { margin-top: 24px; }
.bucket-head { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #e2e8f0; margin-bottom: 12px; }
.bucket-title { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 1rem; color: #334155; font-weight: 600; }
.bucket-icon { font-size: 1.125rem; }
.bucket-count { font-size: 0.75rem; color: #94a3b8; font-weight: 400; margin-left: 4px; }
.btn-add-bucket { background: white; border: 1px dashed #cbd5e1; color: #0ea5e9; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8125rem; }
.btn-add-bucket:hover { background: #f0f9ff; border-color: #0ea5e9; }

.bucket-empty { color: #94a3b8; font-size: 0.875rem; padding: 12px 0; text-align: center; }

.meal-list { display: flex; flex-direction: column; gap: 12px; }
.meal-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; }
.meal-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
.meal-time { font-size: 1rem; font-weight: 600; color: #0f172a; font-variant-numeric: tabular-nums; }
.meal-totals { color: #0ea5e9; font-weight: 600; font-size: 0.9375rem; }
.meal-totals small { color: #64748b; font-weight: 400; margin-left: 6px; }

.item-list { list-style: none; margin: 0; padding: 0; border-top: 1px solid #f1f5f9; padding-top: 8px; }
.item-list li { display: grid; grid-template-columns: 1fr auto auto; gap: 8px; padding: 4px 0; font-size: 0.875rem; color: #475569; align-items: baseline; }
.item-name { color: #1f2937; }
.item-qty { color: #64748b; font-variant-numeric: tabular-nums; }
.item-cal { color: #0f172a; font-weight: 500; font-variant-numeric: tabular-nums; }

.meal-empty-items { color: #94a3b8; font-size: 0.8125rem; margin: 8px 0; font-style: italic; }
.meal-note { margin: 8px 0 0; padding: 6px 10px; background: #fffbeb; border-radius: 6px; font-size: 0.8125rem; color: #78350f; }

.meal-actions { display: flex; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f1f5f9; }
.btn-small { flex: 1; padding: 6px 10px; border: 1px solid #cbd5e1; background: white; border-radius: 6px; font-size: 0.8125rem; cursor: pointer; color: #475569; }
.btn-small:hover { background: #f8fafc; }
.btn-small.btn-danger { color: #dc2626; border-color: #fca5a5; }
.btn-small.btn-danger:hover { background: #fef2f2; }
</style>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import {
  foodService,
  CATEGORY_OPTIONS,
  CATEGORY_LABEL,
  SOURCE_LABEL,
  CONFIDENCE_LABEL,
  type Food,
  type FoodCategory,
  type FoodListMeta,
  type FoodSourceType,
  type FoodConfidenceLevel,
} from '@/services/foodService';
import { ElMessage, ElMessageBox } from 'element-plus';

const router = useRouter();

const foods = ref<Food[]>([]);
const meta = ref<FoodListMeta | null>(null);
const loading = ref(false);
const errorMsg = ref('');

const searchInput = ref('');
const selectedCategory = ref<FoodCategory | ''>('');
const currentPage = ref(1);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
  fetchFoods();
});

// 搜尋輸入：300ms debounce 後重置到第 1 頁再 fetch
watch(searchInput, () => {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    currentPage.value = 1;
    fetchFoods();
  }, 300);
});

// 類別變更：立即重置到第 1 頁再 fetch
watch(selectedCategory, () => {
  currentPage.value = 1;
  fetchFoods();
});

// 換頁：直接 fetch（不重置 page）
watch(currentPage, () => {
  fetchFoods();
});

async function fetchFoods(): Promise<void> {
  loading.value = true;
  errorMsg.value = '';
  try {
    const res = await foodService.list({
      search: searchInput.value || undefined,
      category: selectedCategory.value || undefined,
      page: currentPage.value,
    });
    foods.value = res.data;
    meta.value = res.meta;
  } catch {
    errorMsg.value = '載入食物列表失敗，請稍後再試';
    foods.value = [];
    meta.value = null;
  } finally {
    loading.value = false;
  }
}

function clearFilters(): void {
  searchInput.value = '';
  selectedCategory.value = '';
  currentPage.value = 1;
}

function goPrev(): void {
  if (meta.value && currentPage.value > 1) {
    currentPage.value -= 1;
  }
}

function goNext(): void {
  if (meta.value && currentPage.value < meta.value.last_page) {
    currentPage.value += 1;
  }
}

function goEdit(food: Food): void {
  router.push({ name: 'food-edit', params: { id: food.id } });
}

// === 修正四：資料來源與可信度顯示 ===
function sourceBadgeClass(s: FoodSourceType): string {
  const map: Record<FoodSourceType, string> = {
    system_estimate: 'badge-source-system',
    user_custom:     'badge-source-custom',
    imported:        'badge-source-imported',
    ai_estimate:     'badge-source-ai',
    official:        'badge-source-official',
  };
  return map[s] ?? 'badge-source-custom';
}
function confidenceBadgeClass(c: FoodConfidenceLevel): string {
  const map: Record<FoodConfidenceLevel, string> = {
    high:   'badge-conf-high',
    medium: 'badge-conf-medium',
    low:    'badge-conf-low',
  };
  return map[c] ?? 'badge-conf-low';
}

async function onDelete(food: Food): Promise<void> {
  try {
    await ElMessageBox.confirm(
      `確定要刪除「${food.name}」嗎？此動作無法復原。`,
      '刪除食物',
      { confirmButtonText: '刪除', cancelButtonText: '取消', type: 'warning' },
    );
  } catch {
    return; // 使用者取消
  }

  try {
    await foodService.delete(food.id);
    ElMessage.success('已刪除');
    // 刪掉後重新抓當前頁；若當前頁刪到變空頁，往前一頁
    if (foods.value.length === 1 && currentPage.value > 1) {
      currentPage.value -= 1;
    } else {
      await fetchFoods();
    }
  } catch (e) {
    // 後端 409：被飲食紀錄引用，無法刪除
    const msg = e && typeof e === 'object' && 'response' in e
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ? ((e as any).response?.data?.message ?? '刪除失敗，請稍後再試')
      : '刪除失敗，請稍後再試';
    ElMessage.error(msg);
  }
}
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div class="topbar-left">
        <RouterLink to="/dashboard" class="back-link">← Dashboard</RouterLink>
        <h1>食物資料庫</h1>
      </div>
      <div class="topbar-actions">
        <RouterLink to="/foods/import" class="btn-import">📥 匯入食物</RouterLink>
        <RouterLink to="/foods/new" class="btn-primary">+ 新增食物</RouterLink>
      </div>
    </header>

    <!-- 篩選列 -->
    <div class="filters">
      <input
        v-model="searchInput"
        type="text"
        placeholder="搜尋食物或品牌（如：便當、7-11、星巴克）"
        class="search-input"
      />
      <select v-model="selectedCategory" class="category-select">
        <option value="">全部類別</option>
        <option v-for="opt in CATEGORY_OPTIONS" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>
      <button
        v-if="searchInput || selectedCategory"
        type="button"
        class="btn-clear"
        @click="clearFilters"
      >
        清除篩選
      </button>
    </div>

    <!-- 結果統計 -->
    <p v-if="meta && !loading" class="result-meta">
      共 <strong>{{ meta.total }}</strong> 筆
      <span v-if="searchInput"> · 搜尋「{{ searchInput }}」</span>
      <span v-if="selectedCategory"> · 分類「{{ CATEGORY_LABEL[selectedCategory] }}」</span>
    </p>

    <!-- 列表本體 -->
    <p v-if="loading" class="state">載入中…</p>
    <p v-else-if="errorMsg" class="state error">{{ errorMsg }}</p>
    <div v-else-if="foods.length === 0" class="state empty">
      找不到符合條件的食物。
      <RouterLink to="/foods/new">新增一個？</RouterLink>
    </div>

    <div v-else class="grid">
      <article v-for="food in foods" :key="food.id" class="card">
        <header class="card-top">
          <span class="badge" :class="sourceBadgeClass(food.source_type)">
            {{ SOURCE_LABEL[food.source_type] }}
          </span>
          <span class="category-tag">{{ CATEGORY_LABEL[food.category] }}</span>
        </header>
        <div class="confidence-row">
          <span class="conf-label">可信度</span>
          <span class="badge badge-conf" :class="confidenceBadgeClass(food.confidence_level)">
            {{ CONFIDENCE_LABEL[food.confidence_level] }}
          </span>
        </div>
        <h3 class="card-title">{{ food.name }}</h3>
        <p v-if="food.brand" class="card-brand">{{ food.brand }}</p>
        <p class="card-serving">{{ food.serving_size }} {{ food.serving_unit }}</p>

        <div class="card-calories">
          <span class="kcal-num">{{ food.calories }}</span>
          <span class="kcal-unit">kcal</span>
        </div>

        <dl class="macros">
          <div><dt>蛋白質</dt><dd>{{ food.protein_g }} g</dd></div>
          <div><dt>脂肪</dt><dd>{{ food.fat_g }} g</dd></div>
          <div><dt>碳水</dt><dd>{{ food.carbs_g }} g</dd></div>
        </dl>

        <footer v-if="food.is_owned" class="card-actions">
          <button type="button" class="btn-small" @click="goEdit(food)">編輯</button>
          <button type="button" class="btn-small btn-danger" @click="onDelete(food)">刪除</button>
        </footer>
      </article>
    </div>

    <!-- 分頁 -->
    <nav v-if="meta && meta.last_page > 1" class="pagination">
      <button type="button" class="btn-page" :disabled="currentPage <= 1" @click="goPrev">
        ← 上一頁
      </button>
      <span class="page-indicator">
        {{ meta.current_page }} / {{ meta.last_page }} 頁
      </span>
      <button
        type="button"
        class="btn-page"
        :disabled="currentPage >= meta.last_page"
        @click="goNext"
      >
        下一頁 →
      </button>
    </nav>
  </div>
</template>

<style scoped>
.page { max-width: 960px; margin: 32px auto; padding: 0 24px; }

.topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px; gap: 16px; flex-wrap: wrap; }
.topbar-left { display: flex; align-items: baseline; gap: 16px; }
.topbar h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.back-link { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back-link:hover { color: #0ea5e9; }

.btn-primary { background: #0ea5e9; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 0.9375rem; font-weight: 500; }
.btn-primary:hover { background: #0284c7; }

.topbar-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.btn-import { background: white; color: #0ea5e9; border: 1px solid #0ea5e9; padding: 7px 16px; border-radius: 8px; text-decoration: none; font-size: 0.9375rem; font-weight: 500; }
.btn-import:hover { background: #f0f9ff; }

.filters { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.search-input { flex: 1; min-width: 240px; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9375rem; }
.search-input:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.15); }
.category-select { min-width: 140px; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9375rem; background: white; }
.btn-clear { background: white; border: 1px solid #cbd5e1; padding: 10px 14px; border-radius: 8px; cursor: pointer; color: #475569; font-size: 0.875rem; }
.btn-clear:hover { background: #f1f5f9; }

.result-meta { margin: 0 0 16px; color: #64748b; font-size: 0.875rem; }

.state { text-align: center; padding: 40px 16px; color: #64748b; font-size: 0.9375rem; }
.state.error { color: #dc2626; }
.state.empty a { color: #0ea5e9; text-decoration: none; }
.state.empty a:hover { text-decoration: underline; }

.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }

.card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 8px; }
.card-top { display: flex; align-items: center; justify-content: space-between; }
.badge { font-size: 0.6875rem; padding: 2px 8px; border-radius: 999px; font-weight: 600; }
/* 修正四 + 階段 H：資料來源 badge 顏色 */
.badge-source-system   { background: #dbeafe; color: #1e40af; }
.badge-source-custom   { background: #dcfce7; color: #166534; }
.badge-source-imported { background: #fef3c7; color: #92400e; }
.badge-source-ai       { background: #ede9fe; color: #6d28d9; }  /* 紫色 = AI */
.badge-source-official { background: #e0e7ff; color: #4338ca; }
/* 可信度 badge 顏色 */
.badge-conf-high   { background: #dcfce7; color: #166534; }
.badge-conf-medium { background: #fef3c7; color: #92400e; }
.badge-conf-low    { background: #fee2e2; color: #991b1b; }
.confidence-row { display: flex; align-items: center; gap: 6px; margin-top: -4px; }
.conf-label { font-size: 0.6875rem; color: #94a3b8; }
.badge-conf { padding: 1px 6px; }
.category-tag { font-size: 0.75rem; color: #64748b; }
.card-title { margin: 4px 0 0; font-size: 1rem; color: #0f172a; line-height: 1.4; }
.card-brand { margin: 0; font-size: 0.8125rem; color: #64748b; }
.card-serving { margin: 0; font-size: 0.8125rem; color: #94a3b8; }

.card-calories { display: flex; align-items: baseline; gap: 4px; padding: 8px 0; border-top: 1px dashed #e2e8f0; border-bottom: 1px dashed #e2e8f0; margin: 4px 0; }
.kcal-num { font-size: 1.75rem; font-weight: 700; color: #0ea5e9; }
.kcal-unit { font-size: 0.875rem; color: #64748b; }

.macros { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; margin: 0; }
.macros > div { text-align: center; }
.macros dt { font-size: 0.6875rem; color: #94a3b8; margin: 0; }
.macros dd { margin: 2px 0 0; font-size: 0.875rem; font-weight: 600; color: #1f2937; }

.card-actions { display: flex; gap: 8px; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f5f9; }
.btn-small { flex: 1; padding: 6px 10px; border: 1px solid #cbd5e1; background: white; border-radius: 6px; font-size: 0.8125rem; cursor: pointer; color: #475569; }
.btn-small:hover { background: #f8fafc; }
.btn-small.btn-danger { color: #dc2626; border-color: #fca5a5; }
.btn-small.btn-danger:hover { background: #fef2f2; }

.pagination { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 24px; padding: 16px 0; }
.btn-page { background: white; border: 1px solid #cbd5e1; padding: 8px 14px; border-radius: 8px; cursor: pointer; color: #475569; font-size: 0.875rem; }
.btn-page:hover:not(:disabled) { background: #f1f5f9; }
.btn-page:disabled { opacity: 0.4; cursor: not-allowed; }
.page-indicator { color: #64748b; font-size: 0.875rem; }
</style>

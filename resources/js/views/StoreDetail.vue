<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { storeService, STORE_CATEGORY_LABEL, type Store } from '@/services/storeService';
import {
  CATEGORY_LABEL, CATEGORY_OPTIONS, foodService,
  type Food, type FoodCategory, type FoodPayload,
} from '@/services/foodService';
import { ElMessageBox, ElMessage } from 'element-plus';

const route = useRoute();
const storeId = computed(() => Number(route.params.id));

const loading = ref(false);
const errorMsg = ref('');
const store = ref<Store | null>(null);

// AI 推測店家可以讓使用者編輯菜單；連鎖店原始菜單則鎖定
const isGuessStore = computed(() => {
  return !!store.value?.slug?.startsWith('guess-');
});

// 篩選
const filterCategory = ref<string>('');

const filteredMenu = computed(() => {
  if (!store.value?.menu_items) return [];
  if (!filterCategory.value) return store.value.menu_items;
  return store.value.menu_items.filter((m) => m.category === filterCategory.value);
});

const categoriesInMenu = computed(() => {
  const set = new Set<string>();
  for (const m of store.value?.menu_items ?? []) set.add(m.category);
  return Array.from(set);
});

// === 新增 / 編輯彈窗 ===
const dialogOpen = ref(false);
const dialogMode = ref<'create' | 'edit'>('create');
const editingId = ref<number | null>(null);
const saving = ref(false);

const initialForm = (): FoodPayload => ({
  name: '',
  brand: store.value?.name ?? null,
  category: 'other',
  serving_unit: '份',
  serving_size: 1,
  calories: 0,
  protein_g: 0,
  fat_g: 0,
  carbs_g: 0,
  store_id: storeId.value,
});

const form = reactive<FoodPayload>(initialForm());

// === 預設類別：依照店家 category 給合理初值 ===
function defaultItemCategory(): FoodCategory {
  const c = store.value?.category;
  if (c === 'fast_food') return 'fast_food';
  if (c === 'drink')     return 'drink';
  if (c === 'rice_box')  return 'rice_box';
  if (c === 'noodle')    return 'noodle';
  if (c === 'convenience') return 'convenience';
  if (c === 'snack')     return 'snack';
  return 'other';
}

onMounted(async () => {
  await load();
});

async function load(): Promise<void> {
  loading.value = true;
  errorMsg.value = '';
  try {
    store.value = await storeService.show(storeId.value);
  } catch {
    errorMsg.value = '找不到這家店，可能已被移除';
  } finally {
    loading.value = false;
  }
}

function openAddDialog(): void {
  Object.assign(form, initialForm());
  form.category = defaultItemCategory();
  form.brand = store.value?.name ?? null;
  form.store_id = storeId.value;
  dialogMode.value = 'create';
  editingId.value = null;
  dialogOpen.value = true;
}

function openEditDialog(item: Food): void {
  Object.assign(form, {
    name:         item.name,
    brand:        item.brand,
    category:     item.category,
    serving_unit: item.serving_unit,
    serving_size: item.serving_size,
    calories:     item.calories,
    protein_g:    item.protein_g,
    fat_g:        item.fat_g,
    carbs_g:      item.carbs_g,
    store_id:     storeId.value,
  });
  dialogMode.value = 'edit';
  editingId.value = item.id;
  dialogOpen.value = true;
}

async function onSave(): Promise<void> {
  if (!form.name.trim()) {
    ElMessage.warning('請填寫品項名稱');
    return;
  }
  if (form.calories < 0) {
    ElMessage.warning('熱量不能為負');
    return;
  }
  saving.value = true;
  try {
    if (dialogMode.value === 'edit' && editingId.value !== null) {
      await foodService.update(editingId.value, { ...form });
      ElMessage.success('已更新');
    } else {
      await foodService.create({ ...form });
      ElMessage.success('已新增');
    }
    dialogOpen.value = false;
    await load();
  } catch (e) {
    const msg = e && typeof e === 'object' && 'response' in e
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ? ((e as any).response?.data?.message ?? '儲存失敗')
      : e instanceof Error ? e.message : '儲存失敗';
    ElMessage.error(msg);
  } finally {
    saving.value = false;
  }
}

async function onDelete(item: Food): Promise<void> {
  try {
    await ElMessageBox.confirm(
      `確定要刪除「${item.name}」嗎？`,
      '刪除品項',
      { confirmButtonText: '刪除', cancelButtonText: '取消', type: 'warning' },
    );
  } catch {
    return; // 取消
  }
  try {
    await foodService.delete(item.id);
    ElMessage.success('已刪除');
    await load();
  } catch (e) {
    const msg = e && typeof e === 'object' && 'response' in e
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ? ((e as any).response?.data?.message ?? '刪除失敗')
      : e instanceof Error ? e.message : '刪除失敗';
    ElMessage.error(msg);
  }
}
</script>

<template>
  <div class="page">
    <header class="page-header">
      <RouterLink to="/nearby-stores" class="back">← 附近餐廳</RouterLink>
      <p v-if="loading" class="loading">載入中…</p>

      <template v-else-if="store">
        <div class="store-title">
          <span v-if="store.logo_emoji" class="logo">{{ store.logo_emoji }}</span>
          <h1>{{ store.name }}</h1>
          <span class="badge">{{ STORE_CATEGORY_LABEL[store.category] ?? store.category }}</span>
        </div>
        <p v-if="store.description" class="desc">{{ store.description }}</p>
        <p class="meta-line">
          共 {{ store.menu_items?.length ?? 0 }} 個品項 ·
          <span :class="`conf-${store.confidence_level}`">
            {{ store.confidence_level === 'high' ? '✓ 官方標示為主，可信度高'
              : store.confidence_level === 'medium' ? '〜 業者公告 + 學術估算，可信度中'
              : '・估算值，可信度低' }}
          </span>
        </p>

        <!-- AI 推測店：顯示提示橫幅 + 編輯入口 -->
        <div v-if="isGuessStore" class="guess-banner">
          <p>
            <strong>⚠️ 這是 AI 推測的菜單</strong>，可能不符合實際店家。
            請刪除錯誤品項，或新增實際菜單，這樣熱量計算才會準。
          </p>
          <button class="btn-add-item" @click="openAddDialog">＋ 新增品項</button>
        </div>
      </template>

      <p v-else-if="errorMsg" class="alert">{{ errorMsg }}</p>
    </header>

    <template v-if="store">
      <!-- 類別篩選 -->
      <div v-if="categoriesInMenu.length > 1" class="filters">
        <button
          class="filter-btn"
          :class="{ active: filterCategory === '' }"
          @click="filterCategory = ''"
        >全部</button>
        <button
          v-for="c in categoriesInMenu"
          :key="c"
          class="filter-btn"
          :class="{ active: filterCategory === c }"
          @click="filterCategory = c"
        >
          {{ CATEGORY_LABEL[c as keyof typeof CATEGORY_LABEL] ?? c }}
        </button>
      </div>

      <!-- 菜單 -->
      <ul class="menu-list">
        <li v-for="item in filteredMenu" :key="item.id" class="menu-row">
          <div class="menu-info">
            <strong class="menu-name">{{ item.name }}</strong>
            <div class="menu-meta">
              <span class="serving">{{ item.serving_size }} {{ item.serving_unit }}</span>
              <span class="macros">P {{ item.protein_g }} · F {{ item.fat_g }} · C {{ item.carbs_g }}</span>
            </div>
          </div>
          <div class="menu-cal">
            <strong>{{ item.calories }}</strong>
            <small>kcal</small>
          </div>
          <div class="menu-actions">
            <RouterLink
              :to="`/meals/new?prefill_food_id=${item.id}`"
              class="btn-add"
            >🍽️ 加入</RouterLink>
            <template v-if="isGuessStore">
              <button class="btn-icon edit"  @click="openEditDialog(item)" title="編輯">✏️</button>
              <button class="btn-icon delete" @click="onDelete(item)"     title="刪除">🗑️</button>
            </template>
          </div>
        </li>
      </ul>

      <p v-if="filteredMenu.length === 0" class="empty">這個分類底下沒有品項</p>

      <!-- AI 店：底部再放一個新增按鈕，方便長菜單滾動到底還能加 -->
      <div v-if="isGuessStore" class="bottom-add">
        <button class="btn-add-item-large" @click="openAddDialog">＋ 新增品項</button>
      </div>
    </template>

    <!-- 新增/編輯 Dialog -->
    <el-dialog
      v-model="dialogOpen"
      :title="dialogMode === 'create' ? '新增品項' : '編輯品項'"
      width="92%"
      style="max-width: 460px;"
    >
      <div class="form-grid">
        <label class="field full">
          <span>品項名稱 *</span>
          <input v-model="form.name" type="text" maxlength="100" placeholder="例：雞胸肉沙拉" />
        </label>
        <label class="field">
          <span>類別</span>
          <select v-model="form.category">
            <option v-for="opt in CATEGORY_OPTIONS" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </label>
        <label class="field">
          <span>單位</span>
          <input v-model="form.serving_unit" type="text" maxlength="20" placeholder="份/碗/個/杯" />
        </label>
        <label class="field">
          <span>份量</span>
          <input v-model.number="form.serving_size" type="number" step="0.1" min="0.01" />
        </label>
        <label class="field">
          <span>熱量 (kcal)</span>
          <input v-model.number="form.calories" type="number" min="0" max="99999" />
        </label>
        <label class="field">
          <span>蛋白質 (g)</span>
          <input v-model.number="form.protein_g" type="number" step="0.1" min="0" />
        </label>
        <label class="field">
          <span>脂肪 (g)</span>
          <input v-model.number="form.fat_g" type="number" step="0.1" min="0" />
        </label>
        <label class="field">
          <span>碳水 (g)</span>
          <input v-model.number="form.carbs_g" type="number" step="0.1" min="0" />
        </label>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="dialogOpen = false">取消</button>
        <button class="btn-primary" :disabled="saving" @click="onSave">
          {{ saving ? '儲存中…' : '儲存' }}
        </button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.page { max-width: 760px; margin: 24px auto 64px; padding: 0 24px; }
.page-header { margin-bottom: 20px; }
.back { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back:hover { color: #0ea5e9; }

.loading { color: #64748b; padding: 24px 0; }

.store-title { display: flex; align-items: center; gap: 10px; margin: 8px 0 6px; flex-wrap: wrap; }
.logo { font-size: 2rem; }
.store-title h1 { margin: 0; font-size: 1.75rem; color: #0f172a; }
.badge {
  background: #f1f5f9; color: #475569; padding: 3px 10px;
  border-radius: 999px; font-size: 0.75rem;
}
.desc { color: #475569; margin: 0 0 6px; font-size: 0.9375rem; }
.meta-line { color: #64748b; font-size: 0.8125rem; margin: 0; }
.conf-high   { color: #15803d; }
.conf-medium { color: #b45309; }
.conf-low    { color: #b91c1c; }

.alert { background: #fef2f2; color: #b91c1c; padding: 10px 12px; border-radius: 8px; }

.guess-banner {
  margin-top: 14px;
  padding: 14px 16px;
  background: linear-gradient(135deg, #fffbeb, #fef3c7);
  border: 1px solid #fcd34d;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.guess-banner p { margin: 0; color: #78350f; font-size: 0.875rem; line-height: 1.5; flex: 1 1 280px; }
.guess-banner strong { color: #92400e; }

.btn-add-item {
  background: #6366f1; color: white; border: 0;
  padding: 8px 14px; border-radius: 8px;
  font-size: 0.875rem; cursor: pointer;
  font-weight: 600;
  white-space: nowrap;
}
.btn-add-item:hover { background: #4f46e5; }

.bottom-add { margin-top: 24px; text-align: center; }
.btn-add-item-large {
  background: white; color: #6366f1;
  border: 2px dashed #c7d2fe;
  padding: 14px 28px; border-radius: 12px;
  font-size: 0.9375rem; cursor: pointer;
  font-weight: 600;
  transition: all 0.18s;
}
.btn-add-item-large:hover {
  border-color: #6366f1; background: #eef2ff;
}

.filters { display: flex; gap: 6px; margin: 16px 0; flex-wrap: wrap; }
.filter-btn {
  border: 1px solid #cbd5e1; background: white;
  padding: 5px 12px; border-radius: 999px;
  font-size: 0.8125rem; color: #475569;
  cursor: pointer;
}
.filter-btn:hover { background: #f1f5f9; }
.filter-btn.active { background: #0ea5e9; color: white; border-color: #0ea5e9; }

.menu-list { list-style: none; padding: 0; margin: 0; }
.menu-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 16px;
  align-items: center;
  padding: 14px 16px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  margin-bottom: 8px;
}
.menu-info { min-width: 0; }
.menu-name { color: #0f172a; font-size: 1rem; display: block; }
.menu-meta { display: flex; gap: 10px; align-items: center; margin-top: 4px; flex-wrap: wrap; }
.serving { font-size: 0.75rem; color: #94a3b8; }
.macros { font-size: 0.75rem; color: #64748b; }

.menu-cal { text-align: center; min-width: 70px; }
.menu-cal strong { font-size: 1.25rem; color: #0ea5e9; display: block; line-height: 1; }
.menu-cal small { font-size: 0.6875rem; color: #94a3b8; }

.menu-actions { display: flex; gap: 6px; align-items: center; }

.btn-add {
  background: #0ea5e9; color: white; border: 0;
  padding: 8px 14px; border-radius: 8px;
  font-size: 0.875rem; cursor: pointer;
  text-decoration: none;
  white-space: nowrap;
}
.btn-add:hover { background: #0284c7; }

.btn-icon {
  background: transparent;
  border: 1px solid #e2e8f0;
  padding: 6px 8px;
  border-radius: 8px;
  font-size: 0.875rem;
  cursor: pointer;
  line-height: 1;
}
.btn-icon:hover { background: #f8fafc; }
.btn-icon.delete:hover { background: #fef2f2; border-color: #fecaca; }

.empty { color: #94a3b8; text-align: center; padding: 32px; font-style: italic; }

/* === 表單 === */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 14px;
}
.field { display: flex; flex-direction: column; gap: 4px; font-size: 0.8125rem; }
.field.full { grid-column: 1 / -1; }
.field span { color: #475569; font-weight: 500; }
.field input, .field select {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 0.9375rem;
  font-family: inherit;
  background: white;
}
.field input:focus, .field select:focus {
  outline: 2px solid #6366f1;
  outline-offset: -1px;
  border-color: transparent;
}

.btn-secondary {
  background: white; color: #475569;
  border: 1px solid #cbd5e1;
  padding: 8px 16px; border-radius: 8px;
  font-size: 0.875rem; cursor: pointer;
  margin-right: 8px;
}
.btn-primary {
  background: #6366f1; color: white;
  border: 0; padding: 8px 16px; border-radius: 8px;
  font-size: 0.875rem; cursor: pointer;
  font-weight: 500;
}
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

@media (max-width: 480px) {
  .menu-row { grid-template-columns: 1fr; }
  .menu-cal { text-align: left; }
}
</style>

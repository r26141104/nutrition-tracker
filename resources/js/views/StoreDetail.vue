<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { storeService, STORE_CATEGORY_LABEL, type Store } from '@/services/storeService';
import { CATEGORY_LABEL } from '@/services/foodService';

const route = useRoute();
const storeId = computed(() => Number(route.params.id));

const loading = ref(false);
const errorMsg = ref('');
const store = ref<Store | null>(null);

// 篩選
const filterCategory = ref<string>(''); // 空字串 = 全部

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
          <RouterLink
            :to="`/meals/new?prefill_food_id=${item.id}`"
            class="btn-add"
          >🍽️ 加入</RouterLink>
        </li>
      </ul>

      <p v-if="filteredMenu.length === 0" class="empty">這個分類底下沒有品項</p>
    </template>
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

.btn-add {
  background: #0ea5e9; color: white; border: 0;
  padding: 8px 14px; border-radius: 8px;
  font-size: 0.875rem; cursor: pointer;
  text-decoration: none;
  white-space: nowrap;
}
.btn-add:hover { background: #0284c7; }

.empty { color: #94a3b8; text-align: center; padding: 32px; font-style: italic; }

@media (max-width: 480px) {
  .menu-row { grid-template-columns: 1fr; }
  .menu-cal { text-align: left; }
}
</style>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { storeService, STORE_CATEGORY_LABEL, type Store } from '@/services/storeService';

type MealType = 'all' | 'breakfast' | 'lunch' | 'dinner' | 'snack';

const MEAL_TYPE_OPTIONS: { value: MealType; label: string; emoji: string; subtitle: string }[] = [
  { value: 'all',       label: '全部',       emoji: '🍽️', subtitle: '所有連鎖店' },
  { value: 'breakfast', label: '早餐',       emoji: '🌅', subtitle: '06:00 – 10:00' },
  { value: 'lunch',     label: '午餐',       emoji: '☀️', subtitle: '11:00 – 14:00' },
  { value: 'dinner',    label: '晚餐',       emoji: '🌙', subtitle: '17:00 – 21:00' },
  { value: 'snack',     label: '下午茶/點心', emoji: '🧁', subtitle: '飲料與小食' },
];

// 類別 → 哪些餐別適合
// 根據台灣消費者習慣對應，部分連鎖店全天可吃，會多重歸類
const CATEGORY_TO_MEAL_TYPES: Record<string, MealType[]> = {
  fast_food:   ['breakfast', 'lunch', 'dinner', 'snack'], // 速食店全天
  noodle:      ['lunch', 'dinner'],                       // 麵店主要午晚
  drink:       ['breakfast', 'snack'],                    // 飲料/咖啡店多為早點心
  rice_box:    ['lunch', 'dinner'],                       // 便當主要午晚
  convenience: ['breakfast', 'lunch', 'dinner', 'snack'], // 便利商店全天
  snack:       ['snack'],                                 // 點心類
  other:       [],
};

const stores = ref<Store[]>([]);
const loading = ref(false);
const errorMsg = ref('');
const selectedMealType = ref<MealType>('all');

onMounted(async () => {
  loading.value = true;
  try {
    stores.value = await storeService.list();
  } catch {
    errorMsg.value = '載入連鎖店失敗，請稍後再試';
  } finally {
    loading.value = false;
  }
});

// 過濾掉 AI 推測的店（只顯示有完整菜單的官方連鎖店）
const curatedStores = computed(() =>
  stores.value.filter((s) => !s.slug.startsWith('guess-'))
);

const filteredStores = computed(() => {
  if (selectedMealType.value === 'all') return curatedStores.value;
  return curatedStores.value.filter((s) => {
    const mealTypes = CATEGORY_TO_MEAL_TYPES[s.category] ?? [];
    return mealTypes.includes(selectedMealType.value);
  });
});

const countByMealType = computed<Record<MealType, number>>(() => {
  const counts: Record<MealType, number> = {
    all: curatedStores.value.length,
    breakfast: 0, lunch: 0, dinner: 0, snack: 0,
  };
  for (const s of curatedStores.value) {
    const mealTypes = CATEGORY_TO_MEAL_TYPES[s.category] ?? [];
    for (const mt of mealTypes) {
      if (mt in counts && mt !== 'all') counts[mt]++;
    }
  }
  return counts;
});
</script>

<template>
  <div class="page">
    <header class="page-header">
      <RouterLink to="/" class="back">← Dashboard</RouterLink>
      <h1>🍽️ 依餐別瀏覽</h1>
      <p class="subtitle">挑選你想吃的時段，快速找到合適的連鎖店</p>
    </header>

    <!-- 餐別選擇 tab -->
    <div class="meal-tabs">
      <button
        v-for="opt in MEAL_TYPE_OPTIONS"
        :key="opt.value"
        type="button"
        class="meal-tab"
        :class="{ active: selectedMealType === opt.value }"
        @click="selectedMealType = opt.value"
      >
        <span class="meal-emoji">{{ opt.emoji }}</span>
        <span class="meal-label-wrap">
          <span class="meal-label">{{ opt.label }}</span>
          <span class="meal-subtitle">
            {{ countByMealType[opt.value] }} 家 · {{ opt.subtitle }}
          </span>
        </span>
      </button>
    </div>

    <!-- 載入 / 錯誤狀態 -->
    <p v-if="loading" class="loading">
      <span class="spinner"></span>
      <span>載入連鎖店中…</span>
    </p>
    <div v-else-if="errorMsg" class="empty error">
      ⚠️ {{ errorMsg }}
    </div>

    <!-- 結果列表 -->
    <template v-else>
      <p class="result-summary">
        共 <strong>{{ filteredStores.length }}</strong> 家適合
        <strong>{{ MEAL_TYPE_OPTIONS.find(o => o.value === selectedMealType)?.label }}</strong>
        的連鎖店
      </p>

      <div v-if="filteredStores.length === 0" class="empty">
        這個餐別目前沒有收錄連鎖店。<br>
        試試其他餐別，或回 📍 附近餐廳找小店。
      </div>

      <ul v-else class="store-list">
        <li v-for="s in filteredStores" :key="s.id" class="store-card">
          <RouterLink :to="`/stores/${s.id}`" class="store-link">
            <div class="store-icon">{{ s.logo_emoji ?? '🏪' }}</div>
            <div class="store-info">
              <h3 class="store-name">{{ s.name }}</h3>
              <div class="store-meta">
                <span class="badge">{{ STORE_CATEGORY_LABEL[s.category] ?? s.category }}</span>
                <span v-if="s.menu_items_count > 0" class="meta-count">
                  ✨ {{ s.menu_items_count }} 個品項
                </span>
              </div>
              <p v-if="s.description" class="store-desc">{{ s.description }}</p>
            </div>
            <div class="store-cta">查看菜單 →</div>
          </RouterLink>
        </li>
      </ul>
    </template>
  </div>
</template>

<style scoped>
.page { max-width: 800px; margin: 24px auto 64px; padding: 0 24px; }

.page-header { margin-bottom: 24px; }
.back { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back:hover { color: #6366f1; }
.page-header h1 {
  margin: 8px 0 4px;
  font-size: 1.875rem;
  background: linear-gradient(135deg, #6366f1, #ec4899);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  font-weight: 700;
}
.subtitle { color: #64748b; margin: 0; font-size: 0.9375rem; }

/* 餐別 tabs */
.meal-tabs {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
  margin-bottom: 28px;
}
.meal-tab {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 18px;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(226, 232, 240, 0.7);
  border-radius: 14px;
  font-size: 1rem;
  color: #475569;
  cursor: pointer;
  text-align: left;
  font-family: inherit;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.meal-tab:hover {
  border-color: rgba(196, 181, 253, 0.8);
  background: rgba(245, 243, 255, 0.95);
  transform: translateY(-1px);
}
.meal-tab.active {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
  border-color: transparent;
  box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
  transform: translateY(-2px);
}
.meal-emoji { font-size: 1.5rem; flex-shrink: 0; }
.meal-label-wrap { display: flex; flex-direction: column; min-width: 0; }
.meal-label { font-weight: 600; font-size: 1rem; }
.meal-subtitle { font-size: 0.75rem; opacity: 0.75; margin-top: 2px; }
.meal-tab.active .meal-subtitle { opacity: 0.9; }

/* 載入 / 空狀態 */
.loading {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  padding: 60px 24px;
  color: #64748b;
}
.spinner {
  width: 18px; height: 18px;
  border: 3px solid #e2e8f0;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.empty {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(10px);
  padding: 40px 24px;
  text-align: center;
  border-radius: 12px;
  color: #94a3b8;
  font-size: 0.9375rem;
  line-height: 1.6;
}
.empty.error { color: #b91c1c; background: #fef2f2; }

.result-summary {
  margin: 0 0 16px;
  font-size: 0.9375rem;
  color: #475569;
}
.result-summary strong { color: #6366f1; font-weight: 600; }

/* 店家卡片 */
.store-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
.store-card {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(226, 232, 240, 0.7);
  border-radius: 14px;
  overflow: hidden;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.store-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(99, 102, 241, 0.12);
  border-color: rgba(196, 181, 253, 0.6);
}
.store-link {
  display: grid;
  grid-template-columns: 64px 1fr auto;
  gap: 16px;
  align-items: center;
  padding: 18px 22px;
  text-decoration: none;
  color: inherit;
}
.store-icon {
  font-size: 2.5rem; text-align: center;
  background: linear-gradient(135deg, #f5f3ff, #ede9fe);
  border-radius: 12px;
  width: 64px; height: 64px;
  display: flex; align-items: center; justify-content: center;
}
.store-info { min-width: 0; }
.store-name {
  margin: 0 0 6px;
  font-size: 1.125rem;
  color: #0f172a;
  font-weight: 600;
}
.store-meta {
  display: flex; gap: 8px; align-items: center;
  margin: 0; font-size: 0.8125rem; color: #64748b;
  flex-wrap: wrap;
}
.badge {
  background: #ede9fe; color: #6d28d9;
  padding: 3px 10px; border-radius: 999px;
  font-size: 0.75rem; font-weight: 500;
}
.meta-count { color: #94a3b8; font-size: 0.75rem; }
.store-desc {
  margin: 8px 0 0;
  font-size: 0.8125rem; color: #94a3b8; line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.store-cta {
  color: #6366f1; font-size: 0.875rem; font-weight: 500;
  white-space: nowrap;
}

@media (max-width: 480px) {
  .store-link { grid-template-columns: 56px 1fr; }
  .store-cta { display: none; }
  .store-icon { width: 56px; height: 56px; font-size: 2rem; }
}
</style>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
  foodRecommendationService,
  type FoodRecommendation,
  type RecommendationCategory,
} from '@/services/foodRecommendationService';

const reco = ref<FoodRecommendation | null>(null);
const loading = ref(true);
const errorMsg = ref('');

onMounted(async () => {
  await loadReco();
});

async function loadReco(): Promise<void> {
  loading.value = true;
  errorMsg.value = '';
  try {
    reco.value = await foodRecommendationService.fetchFoodRecommendations();
  } catch {
    errorMsg.value = '載入餐點建議失敗，請稍後再試';
    reco.value = null;
  } finally {
    loading.value = false;
  }
}

const profileMissing = computed(
  () => reco.value !== null && reco.value.remaining === null,
);

const hasGroups = computed(
  () => (reco.value?.recommendation_groups.length ?? 0) > 0,
);

// foods.category → 中文 + 顏色
const FOOD_CATEGORY_LABEL: Record<string, string> = {
  rice_box:    '便當',
  noodle:      '麵店',
  convenience: '便利商店',
  fast_food:   '速食',
  drink:       '飲料',
  snack:       '點心',
  other:       '其他',
};

function categoryLabel(c: string): string {
  return FOOD_CATEGORY_LABEL[c] ?? c;
}

// 修正四：來源 / 可信度顯示
const SOURCE_LABEL: Record<string, string> = {
  system_estimate: '系統估算',
  user_custom:     '我的',
  imported:        '匯入',
  official:        '官方',
};
const CONFIDENCE_LABEL: Record<string, string> = {
  high: '高', medium: '中', low: '低',
};
function sourceLabel(s: string): string {
  return SOURCE_LABEL[s] ?? s;
}
function confidenceLabel(c: string): string {
  return CONFIDENCE_LABEL[c] ?? c;
}

// 推薦群組 category → 顏色
function groupTagType(c: RecommendationCategory): 'primary' | 'success' | 'warning' | 'danger' {
  const map: Record<RecommendationCategory, 'primary' | 'success' | 'warning' | 'danger'> = {
    high_protein: 'primary',
    low_calorie:  'success',
    low_fat:      'warning',
    by_goal:      'primary',
  };
  return map[c] ?? 'primary';
}

// 剩餘營養顏色（負數=已超標 → 紅色）
function remainingColor(value: number): string {
  if (value < 0) return '#dc2626';
  return '#0f172a';
}
function remainingLabel(value: number): string {
  return value < 0 ? '已超標' : '剩餘';
}
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div class="topbar-left">
        <RouterLink to="/dashboard" class="back-link">← Dashboard</RouterLink>
        <h1>餐點建議</h1>
      </div>
    </header>

    <p v-if="loading" class="state">載入中…</p>
    <p v-else-if="errorMsg" class="state error">{{ errorMsg }}</p>

    <template v-else-if="reco">
      <!-- 個人資料未完成 -->
      <el-card v-if="profileMissing" class="reco-card" shadow="never">
        <div class="profile-missing">
          <p>請先完成個人資料設定，才能根據今日剩餘營養額度推薦食物。</p>
          <RouterLink to="/profile">
            <el-button type="primary">前往個人資料設定</el-button>
          </RouterLink>
        </div>
      </el-card>

      <template v-else-if="reco.remaining">
        <!-- 1. 今日剩餘營養（4 格） -->
        <el-card class="reco-card" shadow="never">
          <template #header>
            <span>今日剩餘營養</span>
          </template>
          <div class="remaining-grid">
            <div class="remaining-stat">
              <span class="r-label">{{ remainingLabel(reco.remaining.calories) }}熱量</span>
              <span class="r-num" :style="{ color: remainingColor(reco.remaining.calories) }">
                {{ Math.abs(reco.remaining.calories) }}
              </span>
              <span class="r-unit">kcal</span>
            </div>
            <div class="remaining-stat">
              <span class="r-label">{{ remainingLabel(reco.remaining.protein_g) }}蛋白質</span>
              <span class="r-num" :style="{ color: remainingColor(reco.remaining.protein_g) }">
                {{ Math.abs(reco.remaining.protein_g).toFixed(1) }}
              </span>
              <span class="r-unit">g</span>
            </div>
            <div class="remaining-stat">
              <span class="r-label">{{ remainingLabel(reco.remaining.fat_g) }}脂肪</span>
              <span class="r-num" :style="{ color: remainingColor(reco.remaining.fat_g) }">
                {{ Math.abs(reco.remaining.fat_g).toFixed(1) }}
              </span>
              <span class="r-unit">g</span>
            </div>
            <div class="remaining-stat">
              <span class="r-label">{{ remainingLabel(reco.remaining.carbs_g) }}碳水</span>
              <span class="r-num" :style="{ color: remainingColor(reco.remaining.carbs_g) }">
                {{ Math.abs(reco.remaining.carbs_g).toFixed(1) }}
              </span>
              <span class="r-unit">g</span>
            </div>
          </div>
        </el-card>

        <!-- 2. 動態推薦群組 -->
        <el-empty
          v-if="!hasGroups"
          description="目前沒有可推薦的食物，請先到食物資料庫新增食物。"
          class="empty-groups"
        >
          <RouterLink to="/foods">
            <el-button type="primary">前往食物資料庫</el-button>
          </RouterLink>
        </el-empty>

        <el-card
          v-for="group in reco.recommendation_groups"
          :key="group.category"
          class="group-card"
          shadow="never"
        >
          <template #header>
            <div class="group-head">
              <div class="group-title-wrap">
                <el-tag :type="groupTagType(group.category)" size="default" effect="light">
                  {{ group.title }}
                </el-tag>
              </div>
              <span class="group-meta">{{ group.foods.length }} 項</span>
            </div>
          </template>

          <p class="group-reason">{{ group.reason }}</p>

          <el-empty
            v-if="group.foods.length === 0"
            description="目前沒有符合的食物"
            :image-size="60"
          />

          <div v-else class="food-grid">
            <article
              v-for="food in group.foods"
              :key="food.id"
              class="food-card"
            >
              <header class="food-head">
                <span class="food-name">{{ food.name }}</span>
                <el-tag size="small" type="info" effect="plain">
                  {{ categoryLabel(food.category) }}
                </el-tag>
              </header>
              <div v-if="food.source_type" class="food-source-row">
                <el-tag
                  size="small"
                  :type="food.source_type === 'system_estimate' ? 'primary' : food.source_type === 'imported' ? 'warning' : 'success'"
                  effect="light"
                >
                  {{ sourceLabel(food.source_type) }}
                </el-tag>
                <el-tag
                  v-if="food.confidence_level"
                  size="small"
                  :type="food.confidence_level === 'high' ? 'success' : food.confidence_level === 'medium' ? 'warning' : 'danger'"
                  effect="plain"
                >
                  可信度{{ confidenceLabel(food.confidence_level) }}
                </el-tag>
              </div>
              <p v-if="food.brand" class="food-brand">{{ food.brand }}</p>
              <p class="food-serving">
                {{ food.serving_size }} {{ food.serving_unit }}
              </p>
              <div class="food-cal">
                <span class="cal-num">{{ food.calories }}</span>
                <span class="cal-unit">kcal</span>
              </div>
              <dl class="food-macros">
                <div><dt>蛋白</dt><dd>{{ food.protein_g }}g</dd></div>
                <div><dt>脂肪</dt><dd>{{ food.fat_g }}g</dd></div>
                <div><dt>碳水</dt><dd>{{ food.carbs_g }}g</dd></div>
              </dl>
            </article>
          </div>
        </el-card>
      </template>

      <!-- 3. 注意事項 -->
      <el-alert
        v-for="(note, i) in reco.notes"
        :key="`n-${i}`"
        type="info"
        :title="note"
        :closable="false"
        show-icon
        class="note-alert"
      />
    </template>
  </div>
</template>

<style scoped>
.page { max-width: 960px; margin: 32px auto; padding: 0 24px 64px; }

.topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px; }
.topbar-left { display: flex; align-items: baseline; gap: 16px; }
.topbar h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.back-link { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back-link:hover { color: #0ea5e9; }

.state { text-align: center; padding: 40px 0; color: #64748b; }
.state.error { color: #dc2626; }

.reco-card, .group-card { margin-bottom: 16px; }

.profile-missing { text-align: center; padding: 12px 0 4px; }
.profile-missing p { margin: 0 0 16px; color: #475569; font-size: 0.9375rem; }

/* 剩餘營養 4 格 */
.remaining-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
@media (max-width: 600px) { .remaining-grid { grid-template-columns: repeat(2, 1fr); } }
.remaining-stat { background: #f8fafc; border-radius: 8px; padding: 12px; text-align: center; display: flex; flex-direction: column; gap: 2px; }
.r-label { font-size: 0.75rem; color: #94a3b8; }
.r-num { font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.r-unit { font-size: 0.75rem; color: #64748b; }

/* 群組 */
.group-head { display: flex; justify-content: space-between; align-items: center; }
.group-meta { color: #94a3b8; font-size: 0.8125rem; }
.group-reason { margin: 0 0 12px; padding: 8px 12px; background: #f0f9ff; border-left: 3px solid #0ea5e9; border-radius: 4px; color: #334155; font-size: 0.875rem; line-height: 1.6; }

/* 食物卡片網格 */
.food-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
.food-card { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; display: flex; flex-direction: column; gap: 6px; }
.food-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.food-source-row { display: flex; gap: 4px; flex-wrap: wrap; margin-top: -2px; }
.food-name { font-size: 0.9375rem; font-weight: 600; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.food-brand { margin: 0; font-size: 0.75rem; color: #94a3b8; }
.food-serving { margin: 0; font-size: 0.75rem; color: #64748b; }
.food-cal { display: flex; align-items: baseline; gap: 4px; padding: 6px 0; border-top: 1px dashed #e2e8f0; border-bottom: 1px dashed #e2e8f0; }
.cal-num { font-size: 1.5rem; font-weight: 700; color: #0ea5e9; }
.cal-unit { font-size: 0.75rem; color: #64748b; }
.food-macros { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; margin: 0; }
.food-macros > div { text-align: center; }
.food-macros dt { font-size: 0.6875rem; color: #94a3b8; margin: 0; }
.food-macros dd { margin: 1px 0 0; font-size: 0.8125rem; font-weight: 600; color: #1f2937; }

.empty-groups { padding: 32px 0; }

.note-alert { margin-top: 8px; }
</style>

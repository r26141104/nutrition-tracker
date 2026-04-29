<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { weeklyReportService, type WeeklyReport } from '@/services/weeklyReportService';
import {
  analysisService,
  type WeeklyCorrectionSuggestion,
} from '@/services/analysisService';

const report = ref<WeeklyReport | null>(null);
const correction = ref<WeeklyCorrectionSuggestion | null>(null);
const loading = ref(true);
const errorMsg = ref('');

onMounted(async () => {
  await Promise.all([loadReport(), loadCorrection()]);
});

async function loadReport(): Promise<void> {
  loading.value = true;
  errorMsg.value = '';
  try {
    report.value = await weeklyReportService.fetchCurrentWeeklyReport();
  } catch {
    errorMsg.value = '載入每週報告失敗，請稍後再試';
    report.value = null;
  } finally {
    loading.value = false;
  }
}

async function loadCorrection(): Promise<void> {
  try {
    correction.value = await analysisService.fetchWeeklyCorrectionSuggestions();
  } catch {
    correction.value = null;
  }
}

const hasMealRecords = computed(() => (report.value?.logged_meal_days ?? 0) > 0);

// 修正三：飲食紀錄 < 3 天時視為「資料不足」
const hasInsufficientData = computed(() => (report.value?.logged_meal_days ?? 0) < 3);

// 體重變化的顯示文字 + 顏色
const weightChangeDisplay = computed(() => {
  const w = report.value?.weight_change_kg;
  if (w === null || w === undefined) return { text: '—', color: '#94a3b8' };
  if (Math.abs(w) < 0.1) return { text: '0.0 kg', color: '#64748b' };
  return {
    text: (w > 0 ? '+' : '') + w.toFixed(1) + ' kg',
    color: w < 0 ? '#10b981' : '#f59e0b',
  };
});
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div class="topbar-left">
        <RouterLink to="/dashboard" class="back-link">← Dashboard</RouterLink>
        <h1>每週報告</h1>
      </div>
      <span v-if="report" class="date-range">
        {{ report.week_start }} ～ {{ report.week_end }}
      </span>
    </header>

    <p v-if="loading" class="state">載入中…</p>
    <p v-else-if="errorMsg" class="state error">{{ errorMsg }}</p>

    <template v-else-if="report">
      <!-- 修正三：資料不足提醒 -->
      <el-alert
        v-if="hasInsufficientData"
        type="warning"
        title="本週資料不足"
        :closable="false"
        show-icon
        class="insufficient-alert"
      >
        <template #default>
          <p>本週飲食紀錄少於 3 天，無法產生完整分析。建議連續記錄至少 7 天後再判斷飲食習慣。</p>
        </template>
      </el-alert>

      <!-- 1. 摘要卡（4 格） -->
      <el-card class="summary-card" shadow="never">
        <template #header>
          <span>本週摘要</span>
        </template>

        <div class="stat-grid stat-grid-4">
          <div class="stat">
            <span class="stat-label">飲食紀錄天數</span>
            <span class="stat-num">{{ report.logged_meal_days }}</span>
            <span class="stat-unit">/ 7 天</span>
          </div>
          <div class="stat">
            <span class="stat-label">平均熱量</span>
            <span class="stat-num">{{ report.average_calories }}</span>
            <span class="stat-unit">kcal</span>
          </div>
          <div class="stat">
            <span class="stat-label">體重變化</span>
            <span class="stat-num" :style="{ color: weightChangeDisplay.color }">
              {{ weightChangeDisplay.text }}
            </span>
          </div>
          <div class="stat">
            <span class="stat-label">熱量超標天數</span>
            <span class="stat-num" :style="{ color: report.over_target_days > 0 ? '#dc2626' : '#0f172a' }">
              {{ report.over_target_days }}
            </span>
            <span class="stat-unit">天</span>
          </div>
        </div>
      </el-card>

      <!-- 2. 三大營養素平均 -->
      <el-card v-if="hasMealRecords" class="macro-card" shadow="never">
        <template #header>
          <span>每日平均三大營養素</span>
        </template>
        <div class="stat-grid stat-grid-3">
          <div class="macro macro-protein">
            <span class="macro-label">蛋白質</span>
            <span class="macro-num">{{ report.average_protein_g.toFixed(1) }}</span>
            <span class="macro-unit">g / 天</span>
          </div>
          <div class="macro macro-fat">
            <span class="macro-label">脂肪</span>
            <span class="macro-num">{{ report.average_fat_g.toFixed(1) }}</span>
            <span class="macro-unit">g / 天</span>
          </div>
          <div class="macro macro-carb">
            <span class="macro-label">碳水</span>
            <span class="macro-num">{{ report.average_carbs_g.toFixed(1) }}</span>
            <span class="macro-unit">g / 天</span>
          </div>
        </div>
      </el-card>

      <!-- 3. 最常吃食物 -->
      <el-card class="foods-card" shadow="never">
        <template #header>
          <div class="card-head">
            <span>本週最常吃的食物</span>
            <span class="card-meta">前 5 名</span>
          </div>
        </template>

        <el-empty
          v-if="report.most_frequent_foods.length === 0"
          description="本週還沒有食物紀錄"
        />

        <el-table v-else :data="report.most_frequent_foods" stripe>
          <el-table-column type="index" label="排名" width="80" align="center" />
          <el-table-column prop="food_name" label="食物名稱" />
          <el-table-column label="出現次數" width="120" align="right">
            <template #default="{ row }">
              <span class="num">{{ row.count }}</span> 次
            </template>
          </el-table-column>
        </el-table>
      </el-card>

      <!-- 4. 總結 summary -->
      <el-card class="summary-text-card" shadow="never">
        <template #header>
          <span>本週總結</span>
        </template>
        <ul class="summary-list">
          <li v-for="(s, i) in report.summary" :key="i">{{ s }}</li>
        </ul>
      </el-card>

      <!-- 5. 下週修正建議（階段 F） -->
      <el-card v-if="correction" class="summary-text-card" shadow="never">
        <template #header>
          <span>📋 下週修正建議</span>
        </template>

        <template v-if="correction.has_enough_data">
          <div v-if="correction.strengths.length" class="cor-block cor-strengths">
            <h4>本週優點</h4>
            <ul>
              <li v-for="(s, i) in correction.strengths" :key="i">{{ s }}</li>
            </ul>
          </div>
          <div v-if="correction.issues.length" class="cor-block cor-issues">
            <h4>需要注意</h4>
            <ul>
              <li v-for="(s, i) in correction.issues" :key="i">{{ s }}</li>
            </ul>
          </div>
          <div v-if="correction.action_items.length" class="cor-block cor-actions">
            <h4>下週行動建議（最多 3 項）</h4>
            <ol>
              <li v-for="(s, i) in correction.action_items" :key="i">{{ s }}</li>
            </ol>
          </div>
          <p class="cor-disclaimer">{{ correction.disclaimer }}</p>
        </template>
        <el-empty
          v-else
          :description="correction.action_items?.[0] ?? '資料不足，無法產生建議'"
          :image-size="60"
        />
      </el-card>

      <!-- 6. Disclaimer -->
      <el-alert
        v-for="(w, i) in report.warnings"
        :key="`w-${i}`"
        type="info"
        :title="w"
        :closable="false"
        show-icon
        class="warning-alert"
      />
    </template>
  </div>
</template>

<style scoped>
.page { max-width: 880px; margin: 32px auto; padding: 0 24px 64px; }
.topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px; gap: 12px; flex-wrap: wrap; }
.topbar-left { display: flex; align-items: baseline; gap: 16px; }
.topbar h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.back-link { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back-link:hover { color: #0ea5e9; }
.date-range { color: #475569; font-size: 0.9375rem; font-variant-numeric: tabular-nums; }

.state { text-align: center; padding: 40px 16px; color: #64748b; }
.state.error { color: #dc2626; }

.summary-card, .macro-card, .foods-card, .summary-text-card { margin-bottom: 16px; }
.insufficient-alert { margin-bottom: 16px; }
.insufficient-alert :deep(.el-alert__content) p { margin: 4px 0 0; }
.card-head { display: flex; justify-content: space-between; align-items: baseline; }
.card-meta { color: #94a3b8; font-size: 0.8125rem; }

/* 4 格摘要 */
.stat-grid { display: grid; gap: 12px; }
.stat-grid-4 { grid-template-columns: repeat(4, 1fr); }
.stat-grid-3 { grid-template-columns: repeat(3, 1fr); }
@media (max-width: 600px) {
  .stat-grid-4 { grid-template-columns: repeat(2, 1fr); }
  .stat-grid-3 { grid-template-columns: repeat(3, 1fr); }
}
.stat { background: #f8fafc; border-radius: 8px; padding: 12px; text-align: center; display: flex; flex-direction: column; gap: 2px; }
.stat-label { font-size: 0.75rem; color: #94a3b8; }
.stat-num { font-size: 1.5rem; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; }
.stat-unit { font-size: 0.75rem; color: #64748b; }

/* 三大營養素 */
.macro { padding: 16px; border-radius: 8px; text-align: center; display: flex; flex-direction: column; gap: 4px; }
.macro-protein { background: #fef3c7; }
.macro-fat     { background: #fee2e2; }
.macro-carb    { background: #d1fae5; }
.macro-label { font-size: 0.8125rem; color: #475569; font-weight: 600; }
.macro-num { font-size: 1.5rem; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; }
.macro-unit { font-size: 0.75rem; color: #64748b; }

.num { font-variant-numeric: tabular-nums; font-weight: 600; }

/* 總結文字 */
.summary-list { margin: 0; padding-left: 20px; color: #334155; line-height: 1.8; }
.summary-list li { margin: 4px 0; }

.warning-alert { margin-top: 8px; }

/* 階段 F：下週修正建議 */
.cor-block { margin-bottom: 12px; }
.cor-block h4 { margin: 0 0 6px; font-size: 0.9375rem; color: #334155; }
.cor-block ul, .cor-block ol { margin: 0; padding-left: 22px; line-height: 1.8; font-size: 0.875rem; }
.cor-strengths ul { color: #166534; }
.cor-issues ul { color: #b45309; }
.cor-actions ol { color: #1e40af; }
.cor-disclaimer { margin: 12px 0 0; padding: 8px 12px; background: #f8fafc; border-left: 3px solid #94a3b8; border-radius: 4px; color: #64748b; font-size: 0.8125rem; line-height: 1.6; }
</style>

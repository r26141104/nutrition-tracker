<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, shallowRef } from 'vue';
import { RouterLink } from 'vue-router';
import { bodyRecordService, type BodyRecordTrend } from '@/services/bodyRecordService';
import { dashboardService } from '@/services/dashboardService';

// Chart.js 從 CDN 動態載入
// eslint-disable-next-line @typescript-eslint/no-explicit-any
declare const Chart: any;
const CHART_JS = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';

const loading = ref(true);
const errorMsg = ref('');
const trend = ref<BodyRecordTrend | null>(null);
const calorieData = ref<{ date: string; calories: number; target: number }[]>([]);

const weightChartRef = ref<HTMLCanvasElement | null>(null);
const calorieChartRef = ref<HTMLCanvasElement | null>(null);
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const weightChart = shallowRef<any>(null);
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const calorieChart = shallowRef<any>(null);

function loadChartJs(): Promise<void> {
  return new Promise((resolve) => {
    if (typeof Chart !== 'undefined') {
      resolve();
      return;
    }
    const existing = document.querySelector(`script[src="${CHART_JS}"]`);
    if (existing) {
      existing.addEventListener('load', () => resolve());
      return;
    }
    const script = document.createElement('script');
    script.src = CHART_JS;
    script.onload = () => resolve();
    script.onerror = () => resolve();
    document.head.appendChild(script);
  });
}

onMounted(async () => {
  await loadChartJs();
  await Promise.all([fetchWeightTrend(), fetchCalorieTrend()]);
  loading.value = false;
  setTimeout(renderCharts, 50);
});

onBeforeUnmount(() => {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const wc = weightChart.value as any;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const cc = calorieChart.value as any;
  if (wc && typeof wc.destroy === 'function') wc.destroy();
  if (cc && typeof cc.destroy === 'function') cc.destroy();
});

async function fetchWeightTrend(): Promise<void> {
  try {
    trend.value = await bodyRecordService.fetchBodyRecordTrend(30);
  } catch {
    errorMsg.value = '載入體重資料失敗';
  }
}

/** 今日 PFC 攝取 vs 目標 */
const todayMacros = ref<{
  protein: number; fat: number; carbs: number;
  pTarget: number; fTarget: number; cTarget: number;
} | null>(null);

async function fetchCalorieTrend(): Promise<void> {
  try {
    const dashboard = await dashboardService.fetchTodayDashboard();
    if (dashboard) {
      todayMacros.value = {
        protein: dashboard.totals.protein_g,
        fat:     dashboard.totals.fat_g,
        carbs:   dashboard.totals.carbs_g,
        pTarget: dashboard.targets.protein_g,
        fTarget: dashboard.targets.fat_g,
        cTarget: dashboard.targets.carbs_g,
      };
    }
  } catch {
    todayMacros.value = null;
  }
}

function renderCharts(): void {
  if (typeof Chart === 'undefined') return;
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const textColor = isDark ? '#e2e8f0' : '#475569';
  const gridColor = isDark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(148, 163, 184, 0.25)';

  // === 體重圖 ===
  if (weightChartRef.value && trend.value && trend.value.records.length > 0) {
    const labels = trend.value.records.map((r) => r.record_date.slice(5));
    const weights = trend.value.records.map((r) => Number(r.weight_kg));
    const ctx = weightChartRef.value.getContext('2d');
    if (ctx) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      weightChart.value = new (Chart as any)(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: '體重 (kg)',
            data: weights,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.15)',
            tension: 0.3,
            fill: true,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#6366f1',
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { labels: { color: textColor } } },
          scales: {
            x: { ticks: { color: textColor }, grid: { color: gridColor } },
            y: { ticks: { color: textColor }, grid: { color: gridColor } },
          },
        },
      });
    }
  }

  // === PFC 雙環圖（內圈：實際攝取、外圈：目標） ===
  if (calorieChartRef.value && todayMacros.value) {
    const m = todayMacros.value;
    const ctx = calorieChartRef.value.getContext('2d');
    if (ctx) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      calorieChart.value = new (Chart as any)(ctx, {
        type: 'doughnut',
        data: {
          labels: ['蛋白質 (g)', '脂肪 (g)', '碳水 (g)'],
          datasets: [
            {
              label: '實際攝取',
              data: [m.protein, m.fat, m.carbs],
              backgroundColor: ['#6366f1', '#f59e0b', '#10b981'],
              borderColor: '#ffffff',
              borderWidth: 2,
            },
            {
              label: '目標',
              data: [m.pTarget, m.fTarget, m.cTarget],
              backgroundColor: ['rgba(99,102,241,0.25)', 'rgba(245,158,11,0.25)', 'rgba(16,185,129,0.25)'],
              borderColor: 'rgba(255,255,255,0.5)',
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { labels: { color: textColor } },
            tooltip: {
              callbacks: {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                label: (ctx: any) => `${ctx.dataset.label}: ${ctx.parsed} g`,
              },
            },
          },
        },
      });
    }
  }
}
</script>

<template>
  <div class="page">
    <header class="page-header">
      <RouterLink to="/" class="back">← Dashboard</RouterLink>
      <h1>📈 30 天趨勢</h1>
      <p class="subtitle">用圖表看你最近一個月的體重 + 熱量變化</p>
    </header>

    <p v-if="loading" class="loading">
      <span class="spinner"></span>
      <span>載入趨勢資料中…</span>
    </p>

    <template v-else>
      <section class="card">
        <h2>⚖️ 體重變化</h2>
        <p v-if="!trend || trend.records.length === 0" class="empty">
          還沒有體重紀錄。<RouterLink to="/body-records">去新增一筆 →</RouterLink>
        </p>
        <p v-else-if="trend.records.length === 1" class="empty">
          只有 1 筆紀錄，至少需要 2 筆才能畫圖。
        </p>
        <div v-else class="chart-wrap">
          <canvas ref="weightChartRef"></canvas>
        </div>
        <div v-if="trend && trend.records.length >= 2" class="chart-summary">
          <span>📊 {{ trend.records.length }} 筆紀錄</span>
          <span v-if="trend.insights?.thirty_day_change_kg !== null && trend.insights?.thirty_day_change_kg !== undefined">
            · 30 日變化：
            <strong :class="{
              good: (trend.insights.thirty_day_change_kg ?? 0) < 0,
              warn: (trend.insights.thirty_day_change_kg ?? 0) > 0,
            }">
              {{ trend.insights.thirty_day_change_kg > 0 ? '+' : '' }}{{ trend.insights.thirty_day_change_kg.toFixed(1) }} kg
            </strong>
          </span>
        </div>
      </section>

      <section class="card">
        <h2>🥗 今日三大營養素分布</h2>
        <p v-if="!todayMacros" class="empty">今日還沒記錄飲食</p>
        <div v-else class="chart-wrap chart-wrap-doughnut">
          <canvas ref="calorieChartRef"></canvas>
        </div>
        <div v-if="todayMacros" class="macro-stats">
          <div class="macro-stat protein">
            <span class="macro-label">蛋白質</span>
            <span class="macro-value">{{ todayMacros.protein.toFixed(1) }} g</span>
            <span class="macro-target">目標 {{ todayMacros.pTarget }} g</span>
          </div>
          <div class="macro-stat fat">
            <span class="macro-label">脂肪</span>
            <span class="macro-value">{{ todayMacros.fat.toFixed(1) }} g</span>
            <span class="macro-target">目標 {{ todayMacros.fTarget }} g</span>
          </div>
          <div class="macro-stat carbs">
            <span class="macro-label">碳水</span>
            <span class="macro-value">{{ todayMacros.carbs.toFixed(1) }} g</span>
            <span class="macro-target">目標 {{ todayMacros.cTarget }} g</span>
          </div>
        </div>
      </section>

      <p v-if="errorMsg" class="error-banner">⚠️ {{ errorMsg }}</p>
    </template>
  </div>
</template>

<style scoped>
.page { max-width: 880px; margin: 24px auto 64px; padding: 0 24px; }

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

.card {
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid rgba(226, 232, 240, 0.7);
  border-radius: 14px;
  padding: 24px;
  margin-bottom: 16px;
}
.card h2 { margin: 0 0 16px; font-size: 1.125rem; color: #0f172a; font-weight: 600; }

.chart-wrap {
  position: relative;
  height: 280px;
  width: 100%;
}

.chart-summary {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(226, 232, 240, 0.6);
  display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
  font-size: 0.875rem; color: #64748b;
}
.chart-summary strong { color: #0f172a; }
.chart-summary strong.good { color: #10b981; }
.chart-summary strong.warn { color: #f59e0b; }

.empty { color: #94a3b8; padding: 20px 0; text-align: center; }
.empty a { color: #6366f1; }

.chart-wrap-doughnut { height: 320px; }

.macro-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-top: 16px;
}
.macro-stat {
  display: flex; flex-direction: column; align-items: center;
  padding: 14px;
  border-radius: 10px;
  background: linear-gradient(135deg, #f5f3ff, #ede9fe);
}
.macro-stat.protein { background: linear-gradient(135deg, #eef2ff, #e0e7ff); }
.macro-stat.fat { background: linear-gradient(135deg, #fffbeb, #fef3c7); }
.macro-stat.carbs { background: linear-gradient(135deg, #ecfdf5, #d1fae5); }
.macro-label { font-size: 0.8125rem; color: #64748b; }
.macro-value { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-top: 2px; }
.macro-target { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }

@media (max-width: 480px) {
  .macro-stats { grid-template-columns: 1fr; }
}

.loading {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  padding: 60px; color: #64748b;
}
.spinner {
  width: 18px; height: 18px;
  border: 3px solid #e2e8f0;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.error-banner {
  background: #fef2f2; color: #b91c1c;
  border: 1px solid #fecaca;
  padding: 12px 16px; border-radius: 8px;
  font-size: 0.875rem;
}
</style>

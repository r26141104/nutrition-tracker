<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import {
  dashboardService,
  type DashboardData,
  type MealType,
  type WarningType,
} from '@/services/dashboardService';
import {
  goalProgressService,
  type GoalProgress,
} from '@/services/goalProgressService';
import {
  weeklyReportService,
  type WeeklyReport,
} from '@/services/weeklyReportService';
import {
  bodyRecordService,
  type BodyRecordTrend,
} from '@/services/bodyRecordService';
import {
  analysisService,
  DIET_LEVEL_LABEL,
  DIET_LEVEL_COLOR,
  NUTRITION_LABEL,
  type DietQualityScore,
  type NutritionGap,
} from '@/services/analysisService';
import {
  waterIntakeService,
  type WaterStatus,
} from '@/services/waterIntakeService';
import {
  streakService,
  type StreakInfo,
} from '@/services/streakService';
import { ElMessage } from 'element-plus';

const router = useRouter();
const auth = useAuthStore();

const dashboard = ref<DashboardData | null>(null);
const goal = ref<GoalProgress | null>(null);
const weekly = ref<WeeklyReport | null>(null);
const trend = ref<BodyRecordTrend | null>(null);
const dietScore = ref<DietQualityScore | null>(null);
const nutritionGap = ref<NutritionGap | null>(null);
const water = ref<WaterStatus | null>(null);
const streak = ref<StreakInfo | null>(null);
const loading = ref(true);
const goalLoading = ref(true);
const weeklyLoading = ref(true);
const analysisLoading = ref(true);
const waterAdding = ref(false);
const errorMsg = ref('');
const loggingOut = ref(false);

const MEAL_TYPE_ORDER: Array<{ type: MealType; label: string; icon: string }> = [
  { type: 'breakfast', label: '早餐', icon: '☀️' },
  { type: 'lunch',     label: '午餐', icon: '🍱' },
  { type: 'dinner',    label: '晚餐', icon: '🍲' },
  { type: 'snack',     label: '點心', icon: '🍪' },
];

onMounted(async () => {
  await Promise.all([
    loadDashboard(),
    loadGoalProgress(),
    loadWeekly(),
    loadTrend(),
    loadAnalysis(),
    loadWater(),
    loadStreak(),
  ]);
});

async function loadWater(): Promise<void> {
  try {
    water.value = await waterIntakeService.fetchToday();
  } catch {
    water.value = null;
  }
}

async function loadStreak(): Promise<void> {
  try {
    streak.value = await streakService.fetchStreak();
  } catch {
    streak.value = null;
  }
}

async function onAddWater(amountMl: number): Promise<void> {
  waterAdding.value = true;
  try {
    const res = await waterIntakeService.addIntake(amountMl);
    water.value = res.status;
    ElMessage.success(res.message);
  } catch {
    ElMessage.error('加入水分紀錄失敗');
  } finally {
    waterAdding.value = false;
  }
}

async function onResetWater(): Promise<void> {
  waterAdding.value = true;
  try {
    water.value = await waterIntakeService.resetToday();
    ElMessage.success('已重設今日水分紀錄');
  } catch {
    ElMessage.error('重設失敗');
  } finally {
    waterAdding.value = false;
  }
}

// 水分進度顏色
function waterColor(percent: number): string {
  if (percent >= 100) return '#10b981';
  if (percent >= 70) return '#0ea5e9';
  if (percent >= 40) return '#f59e0b';
  return '#94a3b8';
}

async function loadAnalysis(): Promise<void> {
  analysisLoading.value = true;
  try {
    const [s, g] = await Promise.all([
      analysisService.fetchDietQualityScore(),
      analysisService.fetchNutritionGap(),
    ]);
    dietScore.value = s;
    nutritionGap.value = g;
  } catch {
    dietScore.value = null;
    nutritionGap.value = null;
  } finally {
    analysisLoading.value = false;
  }
}

async function loadTrend(): Promise<void> {
  try {
    trend.value = await bodyRecordService.fetchBodyRecordTrend(30);
  } catch {
    trend.value = null;
  }
}

async function loadDashboard(): Promise<void> {
  loading.value = true;
  errorMsg.value = '';
  try {
    dashboard.value = await dashboardService.fetchTodayDashboard();
  } catch {
    errorMsg.value = '載入今日總覽失敗，請稍後再試';
    dashboard.value = null;
  } finally {
    loading.value = false;
  }
}

async function loadGoalProgress(): Promise<void> {
  goalLoading.value = true;
  try {
    goal.value = await goalProgressService.fetchGoalProgress();
  } catch {
    goal.value = null;
  } finally {
    goalLoading.value = false;
  }
}

async function loadWeekly(): Promise<void> {
  weeklyLoading.value = true;
  try {
    weekly.value = await weeklyReportService.fetchCurrentWeeklyReport();
  } catch {
    weekly.value = null;
  } finally {
    weeklyLoading.value = false;
  }
}

// 體重變化顯示（小卡用）
function weeklyWeightChangeText(): string {
  const w = weekly.value?.weight_change_kg;
  if (w === null || w === undefined) return '—';
  if (Math.abs(w) < 0.1) return '0.0 kg';
  return (w > 0 ? '+' : '') + w.toFixed(1) + ' kg';
}
function weeklyWeightChangeColor(): string {
  const w = weekly.value?.weight_change_kg;
  if (w === null || w === undefined) return '#94a3b8';
  if (Math.abs(w) < 0.1) return '#64748b';
  return w < 0 ? '#10b981' : '#f59e0b';
}

async function onLogout(): Promise<void> {
  loggingOut.value = true;
  await auth.logout();
  router.push({ name: 'login' });
}

// 暗色主題切換
const isDarkMode = ref<boolean>(localStorage.getItem('theme') === 'dark');
function toggleTheme(): void {
  isDarkMode.value = !isDarkMode.value;
  if (isDarkMode.value) {
    document.documentElement.setAttribute('data-theme', 'dark');
    localStorage.setItem('theme', 'dark');
  } else {
    document.documentElement.removeAttribute('data-theme');
    localStorage.setItem('theme', 'light');
  }
}
// 進頁面時套用儲存的主題
if (isDarkMode.value) {
  document.documentElement.setAttribute('data-theme', 'dark');
}

// 把 today_meals 按 meal_type 分桶，沒有的桶就是空陣列
const mealsByType = computed(() => {
  const buckets: Record<MealType, DashboardData['today_meals']> = {
    breakfast: [],
    lunch:     [],
    dinner:    [],
    snack:     [],
  };
  if (!dashboard.value) return buckets;
  for (const meal of dashboard.value.today_meals) {
    buckets[meal.meal_type].push(meal);
  }
  return buckets;
});

const hasMeals = computed(() => (dashboard.value?.today_meals.length ?? 0) > 0);

// el-progress 顏色：> 100% 紅、> 90% 橘、> 70% 綠、其它藍
function progressColor(percent: number, isOver: boolean): string {
  if (isOver) return '#dc2626';
  if (percent >= 90) return '#f59e0b';
  if (percent >= 70) return '#10b981';
  return '#0ea5e9';
}

// 顯示百分比給 el-progress 用：cap 在 100，超過 100 改用 status="exception"
function progressDisplayValue(percent: number): number {
  return Math.min(100, Math.max(0, percent));
}

// 後端 warning type → el-alert type
// danger 對應 el-alert 的 'error'，其他直通
function alertType(t: WarningType): 'info' | 'warning' | 'error' {
  return t === 'danger' ? 'error' : t;
}

// 目標進度 status 的中文標籤（顯示在卡片右上）
function goalStatusLabel(s: GoalProgress['status']): string {
  const map: Record<GoalProgress['status'], string> = {
    no_profile:       '尚未設定',
    no_weight_record: '參考估算',
    in_progress:      '進行中',
    near_goal:        '接近目標',
    reached_goal:     '已達目標',
    maintain:         '維持模式',
  };
  return map[s] ?? '';
}

// 修正六：30 日變化顯示文字
function thirtyDayDisplay(): string {
  const v = trend.value?.insights?.thirty_day_change_kg;
  if (v === null || v === undefined) return '—';
  if (Math.abs(v) < 0.1) return '0.0 kg';
  return (v > 0 ? '+' : '') + v.toFixed(1) + ' kg';
}

// 階段 F：飲食品質分數顏色
function dietScoreColor(level: string): string {
  return DIET_LEVEL_COLOR[level as keyof typeof DIET_LEVEL_COLOR] ?? '#94a3b8';
}
</script>

<template>
  <div class="page">
    <header class="topbar">
      <h1>Dashboard</h1>
      <button class="btn-logout" :disabled="loggingOut" @click="onLogout">
        {{ loggingOut ? '登出中…' : '登出' }}
      </button>
    </header>

    <section class="welcome">
      <h2>嗨，{{ auth.user?.name }} 👋</h2>
    </section>

    <!-- 快速導航 -->
    <nav class="quick-nav">
      <RouterLink to="/profile" class="nav-pill">
        <span class="nav-icon">👤</span>
        <span class="nav-label">個人資料</span>
      </RouterLink>
      <button type="button" class="nav-pill theme-toggle-btn" @click="toggleTheme" :title="isDarkMode ? '切換亮色' : '切換暗色'">
        <span class="nav-icon">{{ isDarkMode ? '🌙' : '☀️' }}</span>
        <span class="nav-label">{{ isDarkMode ? '暗色' : '亮色' }}</span>
      </button>
      <RouterLink to="/foods" class="nav-pill">
        <span class="nav-icon">🍱</span>
        <span class="nav-label">食物資料庫</span>
      </RouterLink>
      <RouterLink to="/meals" class="nav-pill">
        <span class="nav-icon">🍽</span>
        <span class="nav-label">飲食紀錄</span>
      </RouterLink>
      <RouterLink to="/body-records" class="nav-pill">
        <span class="nav-icon">⚖️</span>
        <span class="nav-label">體重紀錄</span>
      </RouterLink>
      <RouterLink to="/weekly-report" class="nav-pill">
        <span class="nav-icon">📊</span>
        <span class="nav-label">每週報告</span>
      </RouterLink>
      <RouterLink to="/exercise-recommendations" class="nav-pill">
        <span class="nav-icon">💪</span>
        <span class="nav-label">運動建議</span>
      </RouterLink>
      <RouterLink to="/food-recommendations" class="nav-pill">
        <span class="nav-icon">🥗</span>
        <span class="nav-label">餐點建議</span>
      </RouterLink>
      <RouterLink to="/foods/vision" class="nav-pill">
        <span class="nav-icon">📷</span>
        <span class="nav-label">拍照辨識</span>
      </RouterLink>
      <RouterLink to="/analysis" class="nav-pill">
        <span class="nav-icon">💡</span>
        <span class="nav-label">分析</span>
      </RouterLink>
      <RouterLink to="/nearby-stores" class="nav-pill">
        <span class="nav-icon">📍</span>
        <span class="nav-label">附近餐廳</span>
      </RouterLink>
      <RouterLink to="/browse-meal" class="nav-pill">
        <span class="nav-icon">🍽️</span>
        <span class="nav-label">依餐別瀏覽</span>
      </RouterLink>
      <RouterLink to="/trends" class="nav-pill">
        <span class="nav-icon">📈</span>
        <span class="nav-label">趨勢圖表</span>
      </RouterLink>
      <RouterLink to="/about" class="nav-pill">
        <span class="nav-icon">ℹ️</span>
        <span class="nav-label">關於</span>
      </RouterLink>
    </nav>

    <p v-if="loading" class="state">載入中…</p>
    <p v-else-if="errorMsg" class="state error">{{ errorMsg }}</p>

    <template v-else-if="dashboard">
      <!-- 個人資料未完成 → 提醒卡 -->
      <el-alert
        v-if="!dashboard.profile_completed"
        type="info"
        :closable="false"
        show-icon
        class="profile-needed"
      >
        <template #title>請先完成個人資料設定</template>
        <template #default>
          <p>填寫身高、體重、生日等資訊後，才能計算每日營養目標與攝取進度。</p>
          <RouterLink to="/profile" class="btn-go-profile">前往個人資料設定 →</RouterLink>
        </template>
      </el-alert>

      <!-- 個人資料完成 → 全套儀表板 -->
      <template v-else-if="dashboard.nutrition_target">
        <!-- 1. 今日攝取總覽（合併原「今日營養目標」+「今日攝取進度」） -->
        <section class="card">
          <div class="card-header">
            <h3>今日攝取總覽</h3>
            <span class="card-meta">{{ dashboard.date }} · 已吃 {{ dashboard.consumed.calories }} / 目標 {{ dashboard.nutrition_target.calories }} kcal</span>
          </div>

          <!-- 目標摘要：4 格緊湊版 -->
          <dl class="target-mini-grid">
            <div><dt>目標熱量</dt><dd>{{ dashboard.nutrition_target.calories }} kcal</dd></div>
            <div><dt>BMR</dt><dd>{{ dashboard.nutrition_target.bmr }} kcal</dd></div>
            <div><dt>TDEE</dt><dd>{{ dashboard.nutrition_target.tdee }} kcal</dd></div>
            <div><dt>目標體重</dt><dd>{{ dashboard.nutrition_target.target_weight_kg }} kg</dd></div>
          </dl>

          <!-- BMR/TDEE 為估算的提示 -->
          <p class="estimate-hint">
            ⓘ BMR / TDEE 為 Mifflin-St Jeor 公式估算（誤差可達 ±10%），實際需求受活動量、睡眠、壓力等影響，建議連續記錄 14～21 天後再依體重趨勢微調。
          </p>

          <h4 class="macro-title">今日攝取進度（單日波動屬正常）</h4>

          <!-- 熱量 -->
          <div class="progress-row">
            <div class="progress-label">
              <span class="label-name">熱量</span>
              <span class="label-stat">
                <strong>{{ dashboard.consumed.calories }}</strong>
                / {{ dashboard.nutrition_target.calories }} kcal
                <span :class="['label-remain', dashboard.is_over.calories ? 'over' : '']">
                  · {{ dashboard.is_over.calories ? '超標' : '剩餘' }}
                  {{ Math.abs(dashboard.remaining.calories) }} kcal
                </span>
              </span>
            </div>
            <el-progress
              :percentage="progressDisplayValue(dashboard.progress_percent.calories)"
              :color="progressColor(dashboard.progress_percent.calories, dashboard.is_over.calories)"
              :status="dashboard.is_over.calories ? 'exception' : ''"
              :stroke-width="14"
              :format="() => dashboard.progress_percent.calories.toFixed(1) + '%'"
            />
          </div>

          <!-- 蛋白質 -->
          <div class="progress-row">
            <div class="progress-label">
              <span class="label-name">蛋白質</span>
              <span class="label-stat">
                <strong>{{ dashboard.consumed.protein_g }}</strong>
                / {{ dashboard.nutrition_target.protein_g }} g
                <span :class="['label-remain', dashboard.is_over.protein_g ? 'over' : '']">
                  · {{ dashboard.is_over.protein_g ? '已達標' : '尚缺' }}
                  {{ Math.abs(dashboard.remaining.protein_g) }} g
                </span>
              </span>
            </div>
            <el-progress
              :percentage="progressDisplayValue(dashboard.progress_percent.protein_g)"
              :color="progressColor(dashboard.progress_percent.protein_g, dashboard.is_over.protein_g)"
              :stroke-width="14"
              :format="() => dashboard.progress_percent.protein_g.toFixed(1) + '%'"
            />
          </div>

          <!-- 脂肪 -->
          <div class="progress-row">
            <div class="progress-label">
              <span class="label-name">脂肪</span>
              <span class="label-stat">
                <strong>{{ dashboard.consumed.fat_g }}</strong>
                / {{ dashboard.nutrition_target.fat_g }} g
                <span :class="['label-remain', dashboard.is_over.fat_g ? 'over' : '']">
                  · {{ dashboard.is_over.fat_g ? '超標' : '剩餘' }}
                  {{ Math.abs(dashboard.remaining.fat_g) }} g
                </span>
              </span>
            </div>
            <el-progress
              :percentage="progressDisplayValue(dashboard.progress_percent.fat_g)"
              :color="progressColor(dashboard.progress_percent.fat_g, dashboard.is_over.fat_g)"
              :status="dashboard.is_over.fat_g ? 'exception' : ''"
              :stroke-width="14"
              :format="() => dashboard.progress_percent.fat_g.toFixed(1) + '%'"
            />
          </div>

          <!-- 碳水 -->
          <div class="progress-row">
            <div class="progress-label">
              <span class="label-name">碳水</span>
              <span class="label-stat">
                <strong>{{ dashboard.consumed.carbs_g }}</strong>
                / {{ dashboard.nutrition_target.carbs_g }} g
                <span :class="['label-remain', dashboard.is_over.carbs_g ? 'over' : '']">
                  · {{ dashboard.is_over.carbs_g ? '超標' : '剩餘' }}
                  {{ Math.abs(dashboard.remaining.carbs_g) }} g
                </span>
              </span>
            </div>
            <el-progress
              :percentage="progressDisplayValue(dashboard.progress_percent.carbs_g)"
              :color="progressColor(dashboard.progress_percent.carbs_g, dashboard.is_over.carbs_g)"
              :status="dashboard.is_over.carbs_g ? 'exception' : ''"
              :stroke-width="14"
              :format="() => dashboard.progress_percent.carbs_g.toFixed(1) + '%'"
            />
          </div>
        </section>

        <!-- 階段 G：水分追蹤 + 連續紀錄火焰 -->
        <el-card class="streak-water-card" shadow="never">
          <template #header>
            <div class="sw-head">
              <span>💧 水分 + 🔥 連續紀錄</span>
            </div>
          </template>

          <div class="sw-grid">
            <!-- 水分進度 -->
            <div class="water-block">
              <div class="water-progress">
                <el-progress
                  type="circle"
                  :percentage="Math.min(100, water?.progress_percent ?? 0)"
                  :color="waterColor(water?.progress_percent ?? 0)"
                  :width="80"
                  :format="() => water ? `${Math.round((water.total_ml / 1000) * 10) / 10}L` : '—'"
                />
              </div>
              <div class="water-info">
                <p class="water-text">
                  <strong>{{ water?.total_ml ?? 0 }}</strong> / {{ water?.target_ml ?? 2000 }} ml
                </p>
                <div class="water-buttons">
                  <el-button size="small" :loading="waterAdding" @click="onAddWater(250)">+250 ml</el-button>
                  <el-button size="small" :loading="waterAdding" @click="onAddWater(500)">+500 ml</el-button>
                  <el-button size="small" link @click="onResetWater">重設</el-button>
                </div>
              </div>
            </div>

            <!-- 連續紀錄火焰 -->
            <div v-if="streak" class="streak-block">
              <div class="streak-row">
                <span class="streak-icon">🔥</span>
                <div class="streak-info">
                  <p class="streak-num">{{ streak.meal_streak }} 天</p>
                  <p class="streak-label">飲食連續紀錄</p>
                </div>
              </div>
              <div class="streak-row">
                <span class="streak-icon">⚖️</span>
                <div class="streak-info">
                  <p class="streak-num">{{ streak.body_record_streak }} 天</p>
                  <p class="streak-label">體重連續紀錄</p>
                </div>
              </div>
              <div v-if="streak.achievements.filter(a => a.achieved).length" class="badges">
                <el-tag
                  v-for="a in streak.achievements.filter(b => b.achieved).slice(0, 3)"
                  :key="`${a.type}-${a.level}`"
                  size="small"
                  type="success"
                  effect="light"
                  class="badge-tag"
                >🏅 {{ a.label }}</el-tag>
              </div>
            </div>
          </div>
        </el-card>

        <!-- 階段 F：飲食品質分數 + 今日營養缺口 摘要小卡 -->
        <el-card class="analysis-card" shadow="never">
          <template #header>
            <div class="analysis-head">
              <span>💡 今日分析摘要</span>
              <RouterLink to="/analysis" class="link">完整分析 →</RouterLink>
            </div>
          </template>

          <p v-if="analysisLoading" class="goal-state">載入中…</p>

          <template v-else>
            <!-- 飲食品質分數 -->
            <div v-if="dietScore?.has_enough_data" class="score-block">
              <div class="score-circle-wrap">
                <el-progress
                  type="circle"
                  :percentage="dietScore.score"
                  :color="dietScoreColor(dietScore.level)"
                  :width="80"
                />
              </div>
              <div class="score-text">
                <p class="score-title">飲食品質分數</p>
                <p class="score-level" :style="{ color: dietScoreColor(dietScore.level) }">
                  {{ DIET_LEVEL_LABEL[dietScore.level] }}（{{ dietScore.score }} / 100）
                </p>
                <p v-if="dietScore.feedback?.[0]" class="score-fb">{{ dietScore.feedback[0] }}</p>
              </div>
            </div>
            <p v-else class="analysis-empty">
              {{ dietScore?.feedback?.[0] ?? '今日飲食品質分數：資料不足' }}
            </p>

            <!-- 營養缺口 -->
            <div v-if="nutritionGap?.has_enough_data" class="gap-summary">
              <div v-if="nutritionGap.main_deficit" class="gap-flag deficit">
                最需要補充：<strong>{{ NUTRITION_LABEL[nutritionGap.main_deficit] }}</strong>
              </div>
              <div v-if="nutritionGap.main_excess" class="gap-flag excess">
                最需要控制：<strong>{{ NUTRITION_LABEL[nutritionGap.main_excess] }}</strong>
              </div>
              <div v-if="!nutritionGap.main_deficit && !nutritionGap.main_excess" class="gap-flag ok">
                整體接近目標
              </div>
            </div>
          </template>
        </el-card>

        <!-- 3. 健康提醒（放在進度條下方） -->
        <el-card class="health-card" shadow="never">
          <template #header>
            <div class="health-head">
              <h3 class="health-title">健康提醒</h3>
              <span class="health-meta">{{ dashboard.warnings.length }} 則</span>
            </div>
          </template>

          <p
            v-if="dashboard.warnings.length === 0"
            class="health-empty"
          >
            目前沒有需要特別注意的提醒。
          </p>

          <div v-else class="health-list">
            <el-alert
              v-for="(w, i) in dashboard.warnings"
              :key="`${w.category}-${i}`"
              :type="alertType(w.type)"
              :title="w.message"
              :closable="false"
              show-icon
            />
          </div>
        </el-card>

        <!-- 3.5 目標進度（放在健康提醒下方、今日餐點上方） -->
        <el-card class="goal-card" shadow="never">
          <template #header>
            <div class="goal-head">
              <span>目標進度</span>
              <span v-if="goal" class="goal-meta">{{ goalStatusLabel(goal.status) }}</span>
            </div>
          </template>

          <p v-if="goalLoading" class="goal-state">載入中…</p>

          <template v-else-if="goal">
            <!-- no_profile: 缺個人資料 -->
            <div v-if="goal.status === 'no_profile'" class="goal-fallback">
              <p class="goal-message">{{ goal.message }}</p>
              <RouterLink to="/profile">
                <el-button type="primary" size="default">前往個人資料設定</el-button>
              </RouterLink>
            </div>

            <!-- maintain: 維持模式 -->
            <div v-else-if="goal.status === 'maintain'" class="goal-stable">
              <el-alert type="success" :title="goal.message" :closable="false" show-icon />
              <div class="goal-stats">
                <el-statistic title="目前體重" :value="goal.current_weight_kg ?? 0" :precision="1" suffix="kg" />
                <el-statistic title="目標體重" :value="goal.target_weight_kg ?? 0" :precision="1" suffix="kg" />
                <el-statistic title="目標 BMI" :value="goal.target_bmi ?? 0" :precision="1" />
              </div>
            </div>

            <!-- reached_goal / near_goal: 接近或達成 -->
            <div v-else-if="goal.status === 'reached_goal' || goal.status === 'near_goal'" class="goal-stable">
              <el-alert
                :type="goal.status === 'reached_goal' ? 'success' : 'info'"
                :title="goal.message"
                :closable="false"
                show-icon
              />
              <div class="goal-stats">
                <el-statistic title="目前體重" :value="goal.current_weight_kg ?? 0" :precision="1" suffix="kg" />
                <el-statistic title="目標體重" :value="goal.target_weight_kg ?? 0" :precision="1" suffix="kg" />
                <el-statistic title="差距" :value="goal.weight_difference_kg ?? 0" :precision="1" suffix="kg" />
              </div>
            </div>

            <!-- in_progress / no_weight_record: 進行中（含估算） -->
            <div v-else>
              <el-alert
                v-if="goal.status === 'no_weight_record'"
                type="warning"
                :closable="false"
                show-icon
                class="goal-alert"
              >
                <template #title>{{ goal.message }}</template>
                <template #default>
                  <RouterLink to="/body-records" class="goal-link">前往體重紀錄 →</RouterLink>
                </template>
              </el-alert>

              <div class="goal-stats">
                <el-statistic title="目前體重" :value="goal.current_weight_kg ?? 0" :precision="1" suffix="kg" />
                <el-statistic title="目標體重" :value="goal.target_weight_kg ?? 0" :precision="1" suffix="kg" />
                <el-statistic title="距離目標" :value="goal.weight_difference_kg ?? 0" :precision="1" suffix="kg" />
                <el-statistic title="預估週數" :value="goal.estimated_weeks ?? 0" suffix="週" />
              </div>

              <div class="goal-date">
                <span class="goal-date-label">預估達標日期</span>
                <span class="goal-date-value">{{ goal.estimated_target_date ?? '—' }}</span>
              </div>

              <p v-if="goal.status === 'in_progress'" class="goal-message">{{ goal.message }}</p>
            </div>

            <!-- 修正六：7 日體重趨勢摘要（資料足夠時顯示） -->
            <el-alert
              v-if="goal.status !== 'no_profile' && trend?.insights?.message"
              :type="trend.insights.has_sufficient_data ? 'info' : 'warning'"
              :title="trend.insights.message"
              :closable="false"
              show-icon
              class="trend-hint"
            >
              <template v-if="trend.insights.has_sufficient_data" #default>
                <p class="trend-detail">
                  7 日平均
                  <strong>{{ trend.insights.seven_day_average_kg?.toFixed(1) ?? '—' }} kg</strong>
                  · 30 日變化
                  <strong>{{ thirtyDayDisplay() }}</strong>
                </p>
              </template>
            </el-alert>

            <!-- 估算免責聲明（除了 no_profile 都顯示，含 BMI 提醒） -->
            <el-alert
              v-if="goal.disclaimer && goal.status !== 'no_profile'"
              type="info"
              :title="goal.disclaimer"
              :closable="false"
              class="goal-disclaimer"
            />
          </template>

          <p v-else class="goal-state error">載入目標進度失敗，請稍後再試。</p>
        </el-card>

        <!-- 3.6 本週狀態小卡 -->
        <el-card class="weekly-mini" shadow="never">
          <template #header>
            <div class="weekly-head">
              <span>本週狀態</span>
              <RouterLink to="/weekly-report" class="link">前往每週報告 →</RouterLink>
            </div>
          </template>

          <p v-if="weeklyLoading" class="goal-state">載入中…</p>

          <div v-else-if="weekly" class="weekly-stats">
            <div class="weekly-stat">
              <span class="weekly-label">飲食紀錄天數</span>
              <span class="weekly-num">{{ weekly.logged_meal_days }}</span>
              <span class="weekly-unit">/ 7 天</span>
            </div>
            <div class="weekly-stat">
              <span class="weekly-label">平均熱量</span>
              <span class="weekly-num">{{ weekly.average_calories }}</span>
              <span class="weekly-unit">kcal</span>
            </div>
            <div class="weekly-stat">
              <span class="weekly-label">體重變化</span>
              <span class="weekly-num" :style="{ color: weeklyWeightChangeColor() }">
                {{ weeklyWeightChangeText() }}
              </span>
            </div>
          </div>

          <p v-else class="goal-state error">載入本週狀態失敗</p>
        </el-card>

        <!-- 4. 今日餐點列表 -->
        <section class="card">
          <div class="card-header">
            <h3>今日餐點</h3>
            <RouterLink to="/meals" class="link">前往飲食紀錄 →</RouterLink>
          </div>

          <div v-if="!hasMeals" class="empty-meals">
            <p>今天還沒有飲食紀錄。</p>
            <RouterLink to="/meals" class="btn-primary-link">前往飲食紀錄</RouterLink>
          </div>

          <template v-else>
            <div
              v-for="bucket in MEAL_TYPE_ORDER"
              :key="bucket.type"
              class="meal-bucket"
            >
              <h4 class="bucket-title">
                <span class="bucket-icon">{{ bucket.icon }}</span>
                {{ bucket.label }}
                <span class="bucket-count">{{ mealsByType[bucket.type].length }} 筆</span>
              </h4>

              <div v-if="mealsByType[bucket.type].length === 0" class="bucket-empty">
                —
              </div>

              <div v-else class="meal-list">
                <article
                  v-for="meal in mealsByType[bucket.type]"
                  :key="meal.id"
                  class="meal-card"
                >
                  <header class="meal-head">
                    <span class="meal-totals">
                      {{ meal.total_calories }} kcal
                      <small>· P {{ meal.total_protein_g }} / F {{ meal.total_fat_g }} / C {{ meal.total_carbs_g }}</small>
                    </span>
                  </header>
                  <ul class="item-list">
                    <li v-for="item in meal.items" :key="item.id">
                      <span class="item-name">{{ item.food_name }}</span>
                      <span class="item-qty">× {{ item.quantity }}</span>
                      <span class="item-cal">{{ item.calories }} kcal</span>
                    </li>
                  </ul>
                </article>
              </div>
            </div>
          </template>
        </section>
      </template>
    </template>
  </div>
</template>

<style scoped>
.page { max-width: 720px; margin: 32px auto; padding: 0 24px 64px; }
.topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px; }
.topbar h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.btn-logout { background: white; color: #dc2626; border: 1px solid #fca5a5; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 0.875rem; }
.btn-logout:hover:not(:disabled) { background: #fef2f2; }
.btn-logout:disabled { opacity: 0.6; cursor: not-allowed; }

.welcome h2 { font-size: 1.25rem; margin: 0 0 16px; color: #0f172a; }

.quick-nav { display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap; }
.nav-pill {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 11px 18px;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  border: 1px solid rgba(226, 232, 240, 0.7);
  border-radius: 999px;
  text-decoration: none; color: #334155;
  font-size: 0.9375rem;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.nav-pill:hover {
  border-color: rgba(99, 102, 241, 0.4);
  color: #4f46e5;
  background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(238, 242, 255, 0.95));
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(99, 102, 241, 0.15);
}
.nav-icon { font-size: 1.125rem; }
.nav-label { font-weight: 500; }

.state { text-align: center; padding: 40px 16px; color: #64748b; font-size: 0.9375rem; }
.state.error { color: #dc2626; }

/* 個人資料未完成提醒 */
.profile-needed { margin-bottom: 16px; }
.profile-needed :deep(.el-alert__content) p { margin: 4px 0 8px; }
.btn-go-profile { display: inline-block; margin-top: 4px; color: #0ea5e9; text-decoration: none; font-weight: 500; }
.btn-go-profile:hover { text-decoration: underline; }

/* 階段 G：水分 + 連續紀錄卡 */
.streak-water-card { margin-top: 16px; }
.sw-head { font-size: 1rem; color: #0f172a; font-weight: 600; }
.sw-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 600px) { .sw-grid { grid-template-columns: 1fr; } }
.water-block { display: flex; align-items: center; gap: 12px; padding: 8px; background: #f0f9ff; border-radius: 8px; }
.water-progress { flex-shrink: 0; }
.water-info { flex: 1; min-width: 0; }
.water-text { margin: 0 0 6px; font-size: 0.875rem; color: #0f172a; font-variant-numeric: tabular-nums; }
.water-text strong { font-size: 1.125rem; color: #0ea5e9; }
.water-buttons { display: flex; gap: 4px; flex-wrap: wrap; }
.streak-block { background: #fffbeb; border-radius: 8px; padding: 10px; }
.streak-row { display: flex; align-items: center; gap: 8px; padding: 4px 0; }
.streak-icon { font-size: 1.5rem; }
.streak-info { flex: 1; }
.streak-num { margin: 0; font-size: 1rem; color: #0f172a; font-weight: 700; }
.streak-label { margin: 0; font-size: 0.75rem; color: #94a3b8; }
.badges { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 6px; }
.badge-tag { font-size: 0.6875rem; }

/* 階段 F：今日分析摘要小卡 */
.analysis-card { margin-top: 16px; }
.analysis-head { display: flex; justify-content: space-between; align-items: baseline; }
.analysis-head > span:first-child { font-size: 1rem; color: #0f172a; font-weight: 600; }
.analysis-empty { margin: 0; padding: 8px 0; color: #94a3b8; font-size: 0.875rem; text-align: center; }
.score-block { display: flex; gap: 16px; align-items: center; padding-bottom: 10px; border-bottom: 1px dashed #f1f5f9; margin-bottom: 10px; flex-wrap: wrap; }
.score-circle-wrap { flex-shrink: 0; }
.score-text { flex: 1; min-width: 180px; }
.score-title { margin: 0 0 4px; font-size: 0.8125rem; color: #94a3b8; }
.score-level { margin: 0 0 4px; font-size: 1rem; font-weight: 700; }
.score-fb { margin: 0; font-size: 0.8125rem; color: #475569; line-height: 1.5; }

.gap-summary { display: flex; gap: 8px; flex-wrap: wrap; }
.gap-flag { padding: 6px 12px; border-radius: 999px; font-size: 0.8125rem; }
.gap-flag.deficit { background: #fef3c7; color: #92400e; }
.gap-flag.excess { background: #fee2e2; color: #991b1b; }
.gap-flag.ok { background: #dcfce7; color: #166534; }
.gap-flag strong { font-weight: 700; }

/* 修正二：BMR/TDEE 估算提示 */
.target-mini-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 12px 0; }
.target-mini-grid > div { background: #f8fafc; border-radius: 6px; padding: 8px; text-align: center; }
.target-mini-grid dt { font-size: 0.6875rem; color: #94a3b8; margin: 0 0 2px; }
.target-mini-grid dd { margin: 0; font-size: 0.9375rem; font-weight: 600; color: #0f172a; }
@media (max-width: 480px) { .target-mini-grid { grid-template-columns: repeat(2, 1fr); } }
.estimate-hint { margin: 8px 0 12px; padding: 8px 12px; background: #fffbeb; border-left: 3px solid #f59e0b; border-radius: 4px; color: #78350f; font-size: 0.8125rem; line-height: 1.6; }

/* 修正六：7 日趨勢提示 */
.trend-hint { margin-top: 12px; }
.trend-detail { margin: 4px 0 0; font-size: 0.875rem; color: #475569; }

/* 目標進度 card */
.goal-card { margin-top: 16px; }
.goal-head { display: flex; justify-content: space-between; align-items: baseline; }
.goal-head > span:first-child { font-size: 1rem; color: #0f172a; font-weight: 600; }
.goal-meta { color: #94a3b8; font-size: 0.8125rem; }
.goal-state { margin: 0; padding: 16px 0; color: #64748b; text-align: center; }
.goal-state.error { color: #dc2626; }
.goal-fallback { text-align: center; padding: 8px 0 4px; }
.goal-fallback .goal-message { margin: 0 0 12px; color: #475569; font-size: 0.9375rem; }
.goal-stable .goal-stats, .goal-stable + .goal-stats { margin-top: 12px; }
.goal-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 12px 0; }
.goal-stats :deep(.el-statistic__head) { font-size: 0.75rem; color: #94a3b8; margin-bottom: 4px; }
.goal-stats :deep(.el-statistic__number) { font-size: 1.25rem; color: #0f172a; font-weight: 700; }
.goal-stats :deep(.el-statistic__content) { font-variant-numeric: tabular-nums; }
@media (max-width: 480px) { .goal-stats { grid-template-columns: repeat(2, 1fr); } }
.goal-alert { margin-bottom: 12px; }
.goal-link { color: #d97706; text-decoration: none; font-weight: 500; }
.goal-link:hover { text-decoration: underline; }
.goal-date { display: flex; align-items: baseline; justify-content: space-between; padding: 10px 14px; background: #f0f9ff; border-radius: 8px; margin-bottom: 12px; }
.goal-date-label { font-size: 0.875rem; color: #475569; }
.goal-date-value { font-size: 1.125rem; font-weight: 600; color: #0ea5e9; font-variant-numeric: tabular-nums; }
.goal-message { margin: 0 0 12px; padding: 10px 14px; background: #f8fafc; border-left: 3px solid #0ea5e9; border-radius: 4px; color: #334155; font-size: 0.875rem; line-height: 1.6; }
.goal-disclaimer { margin-top: 8px; }

/* 本週狀態小卡 */
.weekly-mini { margin-top: 16px; }
.weekly-head { display: flex; justify-content: space-between; align-items: baseline; }
.weekly-head > span:first-child { font-size: 1rem; color: #0f172a; font-weight: 600; }
.weekly-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.weekly-stat { background: #f8fafc; border-radius: 8px; padding: 12px; text-align: center; display: flex; flex-direction: column; gap: 2px; }
.weekly-label { font-size: 0.75rem; color: #94a3b8; }
.weekly-num { font-size: 1.5rem; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; }
.weekly-unit { font-size: 0.75rem; color: #64748b; }

/* 健康提醒 card */
.health-card { margin-top: 16px; }
.health-head { display: flex; justify-content: space-between; align-items: baseline; }
.health-title { margin: 0; font-size: 1rem; color: #0f172a; }
.health-meta { color: #94a3b8; font-size: 0.8125rem; }
.health-empty { margin: 0; padding: 8px 0; color: #64748b; font-size: 0.875rem; text-align: center; }
.health-list { display: flex; flex-direction: column; gap: 8px; }

/* card 通用 */
.card { margin-top: 16px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: white; }
.card-header { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 12px; gap: 12px; flex-wrap: wrap; }
.card h3 { margin: 0; font-size: 1rem; color: #0f172a; }
.card-meta { color: #64748b; font-size: 0.8125rem; }
.link { color: #0ea5e9; font-size: 0.875rem; text-decoration: none; }
.link:hover { text-decoration: underline; }

/* 今日營養目標 */
.target-headline { text-align: center; padding: 16px 0 12px; border-bottom: 1px solid #e2e8f0; margin-bottom: 16px; }
.big-num { font-size: 2.5rem; font-weight: 700; color: #0ea5e9; }
.big-unit { margin-left: 6px; font-size: 1rem; color: #64748b; }
.target-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 0 0 20px; }
.target-grid > div { text-align: center; padding: 8px 0; }
.target-grid dt { font-size: 0.75rem; color: #94a3b8; margin-bottom: 4px; }
.target-grid dd { margin: 0; font-size: 1.125rem; color: #0f172a; font-weight: 600; }
.macro-title { margin: 16px 0 12px; font-size: 0.875rem; color: #475569; font-weight: 600; }
.macro-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 0; }
.macro { padding: 12px; border-radius: 8px; text-align: center; }
.macro-protein { background: #fef3c7; }
.macro-fat     { background: #fee2e2; }
.macro-carb    { background: #d1fae5; }
.macro dt { font-size: 0.75rem; color: #475569; margin-bottom: 4px; }
.macro dd { margin: 0; font-size: 1.125rem; font-weight: 700; color: #0f172a; }

/* 進度條區塊 */
.progress-row { margin-bottom: 18px; }
.progress-row:last-child { margin-bottom: 0; }
.progress-label { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 6px; gap: 8px; flex-wrap: wrap; }
.label-name { font-size: 0.9375rem; color: #334155; font-weight: 600; }
.label-stat { font-size: 0.8125rem; color: #64748b; font-variant-numeric: tabular-nums; }
.label-stat strong { color: #0f172a; font-weight: 600; }
.label-remain { color: #94a3b8; }
.label-remain.over { color: #dc2626; font-weight: 600; }

/* 餐點列表 */
.empty-meals { text-align: center; padding: 24px 0; color: #64748b; }
.empty-meals p { margin: 0 0 12px; }
.btn-primary-link:hover { background: #0284c7; }

.meal-bucket { margin-bottom: 16px; }
.meal-bucket:last-child { margin-bottom: 0; }
.bucket-title { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; font-size: 0.9375rem; color: #334155; padding-bottom: 6px; border-bottom: 1px dashed #e2e8f0; }
.bucket-icon { font-size: 1rem; }
.bucket-count { margin-left: auto; font-size: 0.75rem; color: #94a3b8; font-weight: 400; }
.bucket-empty { color: #cbd5e1; padding: 4px 0 8px; font-size: 0.875rem; }

.meal-list { display: flex; flex-direction: column; gap: 8px; }
.meal-card { background: #f8fafc; border-radius: 8px; padding: 10px 12px; }
.meal-head { display: flex; justify-content: flex-end; margin-bottom: 6px; }
.meal-totals { color: #0ea5e9; font-weight: 600; font-size: 0.875rem; }
.meal-totals small { color: #64748b; font-weight: 400; margin-left: 6px; }
.item-list { list-style: none; margin: 0; padding: 0; }
.item-list li { display: grid; grid-template-columns: 1fr auto auto; gap: 8px; padding: 3px 0; font-size: 0.8125rem; color: #475569; }
.item-name { color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.item-qty { color: #94a3b8; font-variant-numeric: tabular-nums; }
.item-cal { color: #0ea5e9; font-variant-numeric: tabular-nums; font-weight: 500; }

.theme-toggle-btn { cursor: pointer; font-family: inherit; }
</style>

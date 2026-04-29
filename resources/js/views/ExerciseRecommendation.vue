<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
  exerciseRecommendationService,
  type ExerciseRecommendation,
  type GoalType,
} from '@/services/exerciseRecommendationService';

const reco = ref<ExerciseRecommendation | null>(null);
const loading = ref(true);
const errorMsg = ref('');

onMounted(async () => {
  await loadReco();
});

async function loadReco(): Promise<void> {
  loading.value = true;
  errorMsg.value = '';
  try {
    reco.value = await exerciseRecommendationService.fetchExerciseRecommendations();
  } catch {
    errorMsg.value = '載入運動建議失敗，請稍後再試';
    reco.value = null;
  } finally {
    loading.value = false;
  }
}

const profileMissing = computed(() => reco.value !== null && reco.value.goal_type === null);

// goal_type → 中文標籤 + el-tag 顏色
function goalTypeLabel(t: GoalType): string {
  const map: Record<GoalType, string> = {
    lose_fat:    '減脂',
    gain_muscle: '增肌',
    maintain:    '維持',
  };
  return map[t] ?? t;
}
function goalTypeTagType(t: GoalType): 'primary' | 'success' | 'warning' {
  const map: Record<GoalType, 'primary' | 'success' | 'warning'> = {
    lose_fat:    'warning',
    gain_muscle: 'primary',
    maintain:    'success',
  };
  return map[t] ?? 'primary';
}
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div class="topbar-left">
        <RouterLink to="/dashboard" class="back-link">← Dashboard</RouterLink>
        <h1>運動建議</h1>
      </div>
      <el-tag
        v-if="reco?.goal_type"
        :type="goalTypeTagType(reco.goal_type)"
        size="large"
        effect="light"
      >
        {{ goalTypeLabel(reco.goal_type) }}
      </el-tag>
    </header>

    <p v-if="loading" class="state">載入中…</p>
    <p v-else-if="errorMsg" class="state error">{{ errorMsg }}</p>

    <template v-else-if="reco">
      <!-- 個人資料未完成 fallback -->
      <el-card v-if="profileMissing" class="reco-card" shadow="never">
        <div class="profile-missing">
          <p>{{ reco.main_focus }}</p>
          <RouterLink to="/profile">
            <el-button type="primary">前往個人資料設定</el-button>
          </RouterLink>
        </div>
      </el-card>

      <template v-else>
        <!-- 1. 主要方向 -->
        <el-card class="reco-card" shadow="never">
          <template #header>
            <span>主要方向</span>
          </template>
          <p class="main-focus">{{ reco.main_focus }}</p>
        </el-card>

        <!-- 2. 有氧建議 -->
        <el-card class="reco-card" shadow="never">
          <template #header>
            <div class="card-head">
              <span>🏃 有氧建議</span>
              <span class="card-meta">{{ reco.cardio.length }} 點</span>
            </div>
          </template>
          <ul class="reco-list">
            <li v-for="(line, i) in reco.cardio" :key="`c-${i}`">{{ line }}</li>
          </ul>
        </el-card>

        <!-- 3. 肌力訓練建議 -->
        <el-card class="reco-card" shadow="never">
          <template #header>
            <div class="card-head">
              <span>💪 肌力訓練</span>
              <span class="card-meta">{{ reco.resistance_training.length }} 點</span>
            </div>
          </template>
          <ul class="reco-list">
            <li v-for="(line, i) in reco.resistance_training" :key="`r-${i}`">{{ line }}</li>
          </ul>
        </el-card>

        <!-- 4. 一週計畫 -->
        <el-card class="reco-card" shadow="never">
          <template #header>
            <span>📅 一週訓練安排（範例）</span>
          </template>
          <el-timeline>
            <el-timeline-item
              v-for="(item, i) in reco.weekly_plan"
              :key="`w-${i}`"
              :timestamp="item.day"
              placement="top"
              :hollow="item.suggestion.includes('休息')"
              :color="item.suggestion.includes('休息') ? '#94a3b8' : '#0ea5e9'"
            >
              <span class="plan-suggestion">{{ item.suggestion }}</span>
            </el-timeline-item>
          </el-timeline>
        </el-card>

        <!-- 5. 注意事項 -->
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
    </template>
  </div>
</template>

<style scoped>
.page { max-width: 800px; margin: 32px auto; padding: 0 24px 64px; }

.topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px; gap: 12px; flex-wrap: wrap; }
.topbar-left { display: flex; align-items: baseline; gap: 16px; }
.topbar h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.back-link { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back-link:hover { color: #0ea5e9; }

.state { text-align: center; padding: 40px 0; color: #64748b; }
.state.error { color: #dc2626; }

.reco-card { margin-bottom: 16px; }
.card-head { display: flex; justify-content: space-between; align-items: baseline; }
.card-meta { color: #94a3b8; font-size: 0.8125rem; }

.profile-missing { text-align: center; padding: 12px 0 4px; }
.profile-missing p { margin: 0 0 16px; color: #475569; font-size: 0.9375rem; }

.main-focus { margin: 0; padding: 12px 16px; background: #f0f9ff; border-left: 3px solid #0ea5e9; border-radius: 4px; color: #0f172a; line-height: 1.7; font-size: 0.9375rem; }

.reco-list { margin: 0; padding-left: 22px; color: #334155; line-height: 1.9; }
.reco-list li { margin: 6px 0; }

.plan-suggestion { color: #0f172a; font-size: 0.9375rem; }

.note-alert { margin-top: 8px; }
</style>

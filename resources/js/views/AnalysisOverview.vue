<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
  analysisService,
  DIET_LEVEL_LABEL,
  DIET_LEVEL_COLOR,
  CALORIE_SUGGESTION_LABEL,
  NUTRITION_LABEL,
  type CalorieAdjustment,
  type NutritionGap,
  type ProteinDistribution,
  type WeightFluctuation,
  type DietQualityScore,
  type WeeklyCorrectionSuggestion,
} from '@/services/analysisService';

const calorie = ref<CalorieAdjustment | null>(null);
const gap = ref<NutritionGap | null>(null);
const protein = ref<ProteinDistribution | null>(null);
const weight = ref<WeightFluctuation | null>(null);
const score = ref<DietQualityScore | null>(null);
const weekly = ref<WeeklyCorrectionSuggestion | null>(null);

const loading = ref(true);
const errorMsg = ref('');

onMounted(async () => {
  loading.value = true;
  try {
    const [c, g, p, w, s, wk] = await Promise.all([
      analysisService.fetchCalorieAdjustment(),
      analysisService.fetchNutritionGap(),
      analysisService.fetchProteinDistribution(),
      analysisService.fetchWeightFluctuation(),
      analysisService.fetchDietQualityScore(),
      analysisService.fetchWeeklyCorrectionSuggestions(),
    ]);
    calorie.value = c;
    gap.value = g;
    protein.value = p;
    weight.value = w;
    score.value = s;
    weekly.value = wk;
  } catch {
    errorMsg.value = '載入分析資料失敗，請稍後再試';
  } finally {
    loading.value = false;
  }
});

// 進度條顏色（飲食品質分數）
function scoreColor(level: string): string {
  return DIET_LEVEL_COLOR[level as keyof typeof DIET_LEVEL_COLOR] ?? '#94a3b8';
}
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div class="topbar-left">
        <RouterLink to="/dashboard" class="back-link">← Dashboard</RouterLink>
        <h1>💡 個人化分析</h1>
      </div>
    </header>

    <p v-if="loading" class="state">載入中…</p>
    <p v-else-if="errorMsg" class="state error">{{ errorMsg }}</p>

    <template v-else>
      <!-- 1. 飲食品質分數 -->
      <el-card class="ana-card" shadow="never">
        <template #header>
          <div class="card-head">
            <span>📊 今日飲食品質分數</span>
            <span v-if="score" class="card-meta">
              {{ DIET_LEVEL_LABEL[score.level] }}
            </span>
          </div>
        </template>

        <template v-if="score && score.has_enough_data">
          <div class="score-row">
            <el-progress
              type="circle"
              :percentage="score.score"
              :color="scoreColor(score.level)"
              :width="100"
            />
            <div class="score-detail">
              <p class="score-label">總分 {{ score.score }} / 100</p>
              <ul class="score-feedback">
                <li v-for="(f, i) in score.feedback" :key="i">{{ f }}</li>
              </ul>
            </div>
          </div>
          <div class="breakdown">
            <div><dt>蛋白質</dt><dd>{{ score.breakdown.protein }} / 25</dd></div>
            <div><dt>熱量</dt><dd>{{ score.breakdown.calories }} / 25</dd></div>
            <div><dt>脂肪</dt><dd>{{ score.breakdown.fat }} / 15</dd></div>
            <div><dt>碳水</dt><dd>{{ score.breakdown.carbs }} / 10</dd></div>
            <div><dt>紀錄</dt><dd>{{ score.breakdown.meal_logging }} / 15</dd></div>
            <div><dt>點心比</dt><dd>{{ score.breakdown.snack_drink_ratio }} / 10</dd></div>
          </div>
        </template>
        <el-empty v-else :description="score?.feedback?.[0] ?? '資料不足'" :image-size="60" />
      </el-card>

      <!-- 2. 今日營養缺口 -->
      <el-card class="ana-card" shadow="never">
        <template #header>
          <span>🎯 今日營養缺口</span>
        </template>

        <template v-if="gap && gap.has_enough_data && gap.target && gap.consumed && gap.gap">
          <div class="gap-summary">
            <div v-if="gap.main_deficit" class="gap-flag deficit">
              <span class="flag-label">最需要補充</span>
              <strong>{{ NUTRITION_LABEL[gap.main_deficit] }}</strong>
            </div>
            <div v-if="gap.main_excess" class="gap-flag excess">
              <span class="flag-label">最需要控制</span>
              <strong>{{ NUTRITION_LABEL[gap.main_excess] }}</strong>
            </div>
            <div v-if="!gap.main_deficit && !gap.main_excess" class="gap-flag ok">
              <span class="flag-label">狀態</span>
              <strong>整體接近目標</strong>
            </div>
          </div>

          <div class="gap-grid">
            <div class="gap-row">
              <span class="gap-label">熱量</span>
              <span class="gap-stat">{{ gap.consumed.calories }} / {{ gap.target.calories }} kcal</span>
              <span :class="['gap-remaining', gap.gap.calories < 0 ? 'over' : '']">
                {{ gap.gap.calories >= 0 ? `剩 ${gap.gap.calories}` : `超 ${Math.abs(gap.gap.calories)}` }} kcal
              </span>
            </div>
            <div class="gap-row">
              <span class="gap-label">蛋白質</span>
              <span class="gap-stat">{{ gap.consumed.protein_g }} / {{ gap.target.protein_g }} g</span>
              <span :class="['gap-remaining', gap.gap.protein_g < 0 ? 'over' : '']">
                {{ gap.gap.protein_g >= 0 ? `差 ${gap.gap.protein_g}` : `超 ${Math.abs(gap.gap.protein_g)}` }} g
              </span>
            </div>
            <div class="gap-row">
              <span class="gap-label">脂肪</span>
              <span class="gap-stat">{{ gap.consumed.fat_g }} / {{ gap.target.fat_g }} g</span>
              <span :class="['gap-remaining', gap.gap.fat_g < 0 ? 'over' : '']">
                {{ gap.gap.fat_g >= 0 ? `剩 ${gap.gap.fat_g}` : `超 ${Math.abs(gap.gap.fat_g)}` }} g
              </span>
            </div>
            <div class="gap-row">
              <span class="gap-label">碳水</span>
              <span class="gap-stat">{{ gap.consumed.carbs_g }} / {{ gap.target.carbs_g }} g</span>
              <span :class="['gap-remaining', gap.gap.carbs_g < 0 ? 'over' : '']">
                {{ gap.gap.carbs_g >= 0 ? `剩 ${gap.gap.carbs_g}` : `超 ${Math.abs(gap.gap.carbs_g)}` }} g
              </span>
            </div>
          </div>

          <ul v-if="gap.messages.length" class="msg-list">
            <li v-for="(m, i) in gap.messages" :key="i">{{ m }}</li>
          </ul>
        </template>
        <el-empty v-else :description="gap?.message ?? '資料不足'" :image-size="60" />
      </el-card>

      <!-- 3. 蛋白質分配 -->
      <el-card class="ana-card" shadow="never">
        <template #header>
          <span>🥩 今日蛋白質分配</span>
        </template>

        <template v-if="protein && protein.has_enough_data">
          <div class="protein-row">
            <div class="protein-stat"><dt>早餐</dt><dd>{{ protein.by_meal_type.breakfast }} g</dd></div>
            <div class="protein-stat"><dt>午餐</dt><dd>{{ protein.by_meal_type.lunch }} g</dd></div>
            <div class="protein-stat"><dt>晚餐</dt><dd>{{ protein.by_meal_type.dinner }} g</dd></div>
            <div class="protein-stat"><dt>點心</dt><dd>{{ protein.by_meal_type.snack }} g</dd></div>
            <div class="protein-stat protein-total"><dt>合計</dt><dd>{{ protein.total_protein_g }} g</dd></div>
          </div>
          <ul class="msg-list">
            <li v-for="(m, i) in protein.messages" :key="i">{{ m }}</li>
          </ul>
        </template>
        <el-empty v-else :description="protein?.messages?.[0] ?? '資料不足'" :image-size="60" />
      </el-card>

      <!-- 4. 體重波動解釋 -->
      <el-card class="ana-card" shadow="never">
        <template #header>
          <span>⚖️ 體重波動解釋</span>
        </template>

        <template v-if="weight && weight.has_enough_data">
          <el-alert type="info" :title="weight.message" :closable="false" show-icon class="weight-msg" />
          <div class="weight-row">
            <div class="weight-stat"><dt>最新</dt><dd>{{ weight.latest_weight_kg?.toFixed(1) ?? '—' }} kg</dd></div>
            <div class="weight-stat"><dt>上次</dt><dd>{{ weight.previous_weight_kg?.toFixed(1) ?? '—' }} kg</dd></div>
            <div class="weight-stat"><dt>7 日平均</dt><dd>{{ weight.seven_day_average_kg?.toFixed(1) ?? '—' }} kg</dd></div>
          </div>
          <div v-if="weight.possible_reasons.length" class="reasons">
            <span class="reasons-label">可能原因：</span>
            <el-tag
              v-for="(r, i) in weight.possible_reasons"
              :key="i"
              size="small"
              type="info"
              effect="light"
              class="reason-tag"
            >{{ r }}</el-tag>
          </div>
        </template>
        <el-empty v-else :description="weight?.message ?? '資料不足'" :image-size="60" />
      </el-card>

      <!-- 5. 熱量目標修正建議 -->
      <el-card class="ana-card" shadow="never">
        <template #header>
          <div class="card-head">
            <span>🔥 熱量目標修正建議</span>
            <span v-if="calorie?.has_enough_data" class="card-meta">
              近 {{ calorie.period_days }} 天
            </span>
          </div>
        </template>

        <template v-if="calorie && calorie.has_enough_data">
          <el-alert
            :type="calorie.suggestion_type === 'keep' ? 'success' : 'warning'"
            :title="`${CALORIE_SUGGESTION_LABEL[calorie.suggestion_type]}${calorie.suggested_calorie_adjustment !== 0 ? `（建議調整 ${calorie.suggested_calorie_adjustment > 0 ? '+' : ''}${calorie.suggested_calorie_adjustment} kcal/天）` : ''}`"
            :closable="false"
            show-icon
          >
            <template #default>
              <p class="cal-message">{{ calorie.message }}</p>
            </template>
          </el-alert>

          <div class="cal-detail">
            <div class="cal-stat"><dt>平均每日熱量</dt><dd>{{ calorie.average_daily_calories }} kcal</dd></div>
            <div class="cal-stat"><dt>實際變化</dt><dd>{{ calorie.actual_weight_change_kg }} kg</dd></div>
            <div class="cal-stat"><dt>預期變化</dt><dd>{{ calorie.expected_weight_change_kg }} kg</dd></div>
          </div>

          <p class="disclaimer">{{ calorie.disclaimer }}</p>
        </template>
        <el-empty v-else :description="calorie?.message ?? '資料不足'" :image-size="60" />
      </el-card>

      <!-- 6. 下週修正建議 -->
      <el-card class="ana-card" shadow="never">
        <template #header>
          <span>📋 下週修正建議</span>
        </template>

        <template v-if="weekly && weekly.has_enough_data">
          <div v-if="weekly.strengths.length" class="block strengths">
            <h4>本週優點</h4>
            <ul>
              <li v-for="(s, i) in weekly.strengths" :key="i">{{ s }}</li>
            </ul>
          </div>
          <div v-if="weekly.issues.length" class="block issues">
            <h4>需要注意</h4>
            <ul>
              <li v-for="(s, i) in weekly.issues" :key="i">{{ s }}</li>
            </ul>
          </div>
          <div v-if="weekly.action_items.length" class="block actions">
            <h4>下週行動建議（最多 3 項）</h4>
            <ol>
              <li v-for="(s, i) in weekly.action_items" :key="i">{{ s }}</li>
            </ol>
          </div>
          <p class="disclaimer">{{ weekly.disclaimer }}</p>
        </template>
        <el-empty v-else :description="weekly?.action_items?.[0] ?? '資料不足'" :image-size="60" />
      </el-card>
    </template>
  </div>
</template>

<style scoped>
.page { max-width: 880px; margin: 32px auto; padding: 0 24px 64px; }

.topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px; }
.topbar-left { display: flex; align-items: baseline; gap: 16px; }
.topbar h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.back-link { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back-link:hover { color: #0ea5e9; }

.state { text-align: center; padding: 40px 0; color: #64748b; }
.state.error { color: #dc2626; }

.ana-card { margin-bottom: 16px; }
.card-head { display: flex; justify-content: space-between; align-items: baseline; }
.card-meta { color: #94a3b8; font-size: 0.8125rem; }

/* 飲食品質分數 */
.score-row { display: flex; align-items: center; gap: 24px; flex-wrap: wrap; margin-bottom: 16px; }
.score-detail { flex: 1; min-width: 200px; }
.score-label { font-size: 1.125rem; font-weight: 600; color: #0f172a; margin: 0 0 8px; }
.score-feedback { margin: 0; padding-left: 20px; color: #475569; font-size: 0.875rem; line-height: 1.7; }
.breakdown { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; padding-top: 12px; border-top: 1px dashed #f1f5f9; }
@media (max-width: 600px) { .breakdown { grid-template-columns: repeat(3, 1fr); } }
.breakdown > div { background: #f8fafc; border-radius: 6px; padding: 8px; text-align: center; }
.breakdown dt { font-size: 0.6875rem; color: #94a3b8; margin: 0 0 2px; }
.breakdown dd { margin: 0; font-size: 0.875rem; font-weight: 600; color: #0f172a; }

/* 營養缺口 */
.gap-summary { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.gap-flag { padding: 8px 12px; border-radius: 8px; display: flex; flex-direction: column; gap: 2px; min-width: 120px; }
.gap-flag.deficit { background: #fef3c7; }
.gap-flag.excess { background: #fee2e2; }
.gap-flag.ok { background: #dcfce7; }
.flag-label { font-size: 0.75rem; color: #64748b; }
.gap-flag strong { color: #0f172a; font-size: 0.9375rem; }

.gap-grid { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
.gap-row { display: grid; grid-template-columns: 60px 1fr auto; gap: 8px; align-items: center; padding: 6px 10px; background: #f8fafc; border-radius: 6px; }
.gap-label { font-size: 0.875rem; color: #475569; font-weight: 600; }
.gap-stat { font-size: 0.875rem; color: #1f2937; font-variant-numeric: tabular-nums; }
.gap-remaining { font-size: 0.8125rem; color: #10b981; font-weight: 600; font-variant-numeric: tabular-nums; }
.gap-remaining.over { color: #dc2626; }

.msg-list { margin: 12px 0 0; padding-left: 20px; color: #334155; line-height: 1.7; font-size: 0.875rem; }

/* 蛋白質 */
.protein-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 12px; }
@media (max-width: 600px) { .protein-row { grid-template-columns: repeat(3, 1fr); } }
.protein-stat { background: #f8fafc; border-radius: 6px; padding: 10px; text-align: center; }
.protein-stat dt { font-size: 0.75rem; color: #94a3b8; margin: 0 0 2px; }
.protein-stat dd { margin: 0; font-size: 1rem; font-weight: 600; color: #0f172a; }
.protein-total { background: #f0f9ff; }
.protein-total dd { color: #0ea5e9; }

/* 體重波動 */
.weight-msg { margin-bottom: 12px; }
.weight-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px; }
.weight-stat { background: #f8fafc; border-radius: 6px; padding: 10px; text-align: center; }
.weight-stat dt { font-size: 0.75rem; color: #94a3b8; margin: 0 0 2px; }
.weight-stat dd { margin: 0; font-size: 1rem; font-weight: 600; color: #0f172a; }
.reasons { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.reasons-label { font-size: 0.875rem; color: #475569; }
.reason-tag { font-size: 0.8125rem; }

/* 熱量目標 */
.cal-message { margin: 4px 0 0; font-size: 0.875rem; }
.cal-detail { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 12px; }
.cal-stat { background: #f8fafc; border-radius: 6px; padding: 10px; text-align: center; }
.cal-stat dt { font-size: 0.75rem; color: #94a3b8; margin: 0 0 2px; }
.cal-stat dd { margin: 0; font-size: 0.9375rem; font-weight: 600; color: #0f172a; }

/* 下週修正 */
.block { margin-bottom: 12px; }
.block h4 { margin: 0 0 6px; font-size: 0.9375rem; color: #334155; }
.block ul, .block ol { margin: 0; padding-left: 22px; line-height: 1.8; font-size: 0.875rem; }
.block.strengths ul { color: #166534; }
.block.issues ul { color: #b45309; }
.block.actions ol { color: #1e40af; }

.disclaimer { margin: 12px 0 0; padding: 8px 12px; background: #f8fafc; border-left: 3px solid #94a3b8; border-radius: 4px; color: #64748b; font-size: 0.8125rem; line-height: 1.6; }
</style>

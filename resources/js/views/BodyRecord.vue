<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { AxiosError } from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  bodyRecordService,
  type BodyRecord,
  type BodyRecordPayload,
  type BodyRecordTrend,
  type TrendDays,
} from '@/services/bodyRecordService';
import { profileService, type UserProfile } from '@/services/profileService';
import {
  analysisService,
  type WeightFluctuation,
} from '@/services/analysisService';

// === state ===
const records = ref<BodyRecord[]>([]);
const profile = ref<UserProfile | null>(null);
const loading = ref(true);
const submitting = ref(false);
const editingId = ref<number | null>(null);

// === trend state ===
const trend = ref<BodyRecordTrend | null>(null);
const trendLoading = ref(false);
const trendDays = ref<TrendDays>(30);

// 階段 F：體重波動解釋
const fluctuation = ref<WeightFluctuation | null>(null);

interface FormState {
  record_date: string;
  weight_kg: number;
  note: string;
  // 階段 G：身體量測補完（全部 optional）
  waist_cm: number | null;
  hip_cm: number | null;
  chest_cm: number | null;
  arm_cm: number | null;
  thigh_cm: number | null;
  body_fat_percent: number | null;
  muscle_mass_kg: number | null;
}

const todayStr = new Date().toISOString().slice(0, 10);

const form = reactive<FormState>({
  record_date: todayStr,
  weight_kg: 60,
  note: '',
  // 階段 G
  waist_cm: null,
  hip_cm: null,
  chest_cm: null,
  arm_cm: null,
  thigh_cm: null,
  body_fat_percent: null,
  muscle_mass_kg: null,
});

// 進階欄位是否展開（預設關，避免畫面太擠）
const showAdvanced = ref(false);

const profileComplete = computed(
  () => profile.value !== null
    && profile.value.height_cm !== null
    && Number(profile.value.height_cm) > 0,
);

// === lifecycle ===
onMounted(async () => {
  await Promise.all([loadProfile(), loadRecords(), loadTrend(), loadFluctuation()]);
  loading.value = false;
});

async function loadFluctuation(): Promise<void> {
  try {
    fluctuation.value = await analysisService.fetchWeightFluctuation();
  } catch {
    fluctuation.value = null;
  }
}

// 切換天數時重新載入趨勢
watch(trendDays, () => {
  loadTrend();
});

async function loadProfile(): Promise<void> {
  try {
    profile.value = await profileService.getProfile();
  } catch {
    profile.value = null;
  }
}

async function loadRecords(): Promise<void> {
  try {
    records.value = await bodyRecordService.fetchBodyRecords();
  } catch {
    records.value = [];
    ElMessage.error('載入體重紀錄失敗，請稍後再試');
  }
}

async function loadTrend(): Promise<void> {
  trendLoading.value = true;
  try {
    trend.value = await bodyRecordService.fetchBodyRecordTrend(trendDays.value);
  } catch {
    trend.value = null;
  } finally {
    trendLoading.value = false;
  }
}

// === form actions ===
async function onSubmit(): Promise<void> {
  if (!form.record_date) {
    ElMessage.warning('請選擇日期');
    return;
  }
  if (!form.weight_kg || form.weight_kg < 20 || form.weight_kg > 500) {
    ElMessage.warning('體重需介於 20～500 kg');
    return;
  }

  submitting.value = true;

  const payload: BodyRecordPayload = {
    record_date: form.record_date,
    weight_kg: form.weight_kg,
    note: form.note.trim() === '' ? null : form.note.trim(),
    // 階段 G：只送有值的欄位（保留 null 表示「不填」）
    waist_cm: form.waist_cm,
    hip_cm: form.hip_cm,
    chest_cm: form.chest_cm,
    arm_cm: form.arm_cm,
    thigh_cm: form.thigh_cm,
    body_fat_percent: form.body_fat_percent,
    muscle_mass_kg: form.muscle_mass_kg,
  };

  try {
    if (editingId.value !== null) {
      await bodyRecordService.updateBodyRecord(editingId.value, payload);
      ElMessage.success('已更新體重紀錄');
    } else {
      await bodyRecordService.createBodyRecord(payload);
      ElMessage.success('已儲存體重紀錄');
    }
    cancelEdit();
    await loadRecords();
  } catch (e) {
    handleError(e);
  } finally {
    submitting.value = false;
  }
}

function onEdit(record: BodyRecord): void {
  editingId.value = record.id;
  form.record_date = record.record_date;
  form.weight_kg = record.weight_kg;
  form.note = record.note ?? '';
  // 階段 G
  form.waist_cm = record.waist_cm;
  form.hip_cm = record.hip_cm;
  form.chest_cm = record.chest_cm;
  form.arm_cm = record.arm_cm;
  form.thigh_cm = record.thigh_cm;
  form.body_fat_percent = record.body_fat_percent;
  form.muscle_mass_kg = record.muscle_mass_kg;
  // 如果該紀錄有任何進階欄位，自動展開進階區
  showAdvanced.value = !!(record.waist_cm || record.body_fat_percent || record.muscle_mass_kg);
  // 滑到頁面頂端讓使用者看見表單
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit(): void {
  editingId.value = null;
  form.record_date = todayStr;
  form.weight_kg = 60;
  form.note = '';
  // 階段 G 重置
  form.waist_cm = null;
  form.hip_cm = null;
  form.chest_cm = null;
  form.arm_cm = null;
  form.thigh_cm = null;
  form.body_fat_percent = null;
  form.muscle_mass_kg = null;
  showAdvanced.value = false;
}

async function onDelete(record: BodyRecord): Promise<void> {
  try {
    await ElMessageBox.confirm(
      `確定要刪除 ${record.record_date} 的紀錄（${record.weight_kg} kg）嗎？此動作無法復原。`,
      '確認刪除',
      {
        confirmButtonText: '刪除',
        cancelButtonText: '取消',
        type: 'warning',
      },
    );
  } catch {
    return; // 使用者點取消
  }

  try {
    await bodyRecordService.deleteBodyRecord(record.id);
    ElMessage.success('已刪除');
    if (editingId.value === record.id) cancelEdit();
    await loadRecords();
  } catch (e) {
    handleError(e);
  }
}

// === SVG 折線圖座標計算 ===
interface ChartPoint {
  x: number;
  y: number;
  record_date: string;
  weight_kg: number;
  bmi: number;
}

interface ChartGeometry {
  W: number; H: number;
  PL: number; PR: number; PT: number; PB: number;
  plotW: number; plotH: number;
  points: ChartPoint[];
  linePath: string;
  targetY: number | null;
  ticks: Array<{ value: number; y: number }>;
  minW: number; maxW: number;
}

const chart = computed<ChartGeometry | null>(() => {
  const t = trend.value;
  if (!t || t.records.length === 0) return null;

  const records = t.records;
  const targetW = t.target_weight_kg;

  // Y 軸範圍：紀錄 + 目標體重一起算，並加 padding
  const weights = records.map((r) => r.weight_kg);
  const allWeights = targetW !== null ? [...weights, targetW] : weights;
  let minW = Math.min(...allWeights);
  let maxW = Math.max(...allWeights);
  const span = maxW - minW;
  if (span === 0) {
    minW -= 1;
    maxW += 1;
  } else {
    minW -= span * 0.1;
    maxW += span * 0.1;
  }
  const range = maxW - minW || 1;

  // Layout（用 viewBox，會跟著容器縮放）
  const W = 700;
  const H = 240;
  const PL = 50;
  const PR = 60; // 右側留空間給「目標 47.5」標籤
  const PT = 20;
  const PB = 30;
  const plotW = W - PL - PR;
  const plotH = H - PT - PB;

  // 紀錄點映射
  const n = records.length;
  const points: ChartPoint[] = records.map((r, i) => {
    const x = n === 1 ? PL + plotW / 2 : PL + (i / (n - 1)) * plotW;
    const y = PT + (1 - (r.weight_kg - minW) / range) * plotH;
    return { x, y, record_date: r.record_date, weight_kg: r.weight_kg, bmi: r.bmi };
  });

  // 折線 path
  const linePath = points
    .map((p, i) => (i === 0 ? 'M' : 'L') + p.x.toFixed(1) + ',' + p.y.toFixed(1))
    .join(' ');

  // 目標體重水平虛線（只有在範圍內才畫）
  const targetY = targetW !== null && targetW >= minW && targetW <= maxW
    ? PT + (1 - (targetW - minW) / range) * plotH
    : null;

  // Y 軸刻度（5 個）
  const ticks = [0, 0.25, 0.5, 0.75, 1].map((tt) => ({
    value: minW + tt * range,
    y: PT + (1 - tt) * plotH,
  }));

  return { W, H, PL, PR, PT, PB, plotW, plotH, points, linePath, targetY, ticks, minW, maxW };
});

// === 修正六：30 日變化顯示 ===
function thirtyDayText(): string {
  const v = trend.value?.insights?.thirty_day_change_kg;
  if (v === null || v === undefined) return '—';
  if (Math.abs(v) < 0.1) return '0.0 kg';
  return (v > 0 ? '+' : '') + v.toFixed(1) + ' kg';
}
function thirtyDayColor(): string {
  const v = trend.value?.insights?.thirty_day_change_kg;
  if (v === null || v === undefined) return '#94a3b8';
  if (Math.abs(v) < 0.1) return '#64748b';
  return v < 0 ? '#10b981' : '#f59e0b';
}

// === error handling ===
function handleError(e: unknown): void {
  if (e instanceof AxiosError) {
    const status = e.response?.status;
    const errors = e.response?.data?.errors as Record<string, string[]> | undefined;

    if (status === 422 && errors) {
      // 後端針對個人資料缺失給的 422
      if (errors.profile) {
        ElMessage.error(errors.profile[0]);
        return;
      }
      // 一般 validation 錯誤：取第一個欄位的第一條訊息
      const firstField = Object.keys(errors)[0];
      if (firstField && errors[firstField][0]) {
        ElMessage.error(errors[firstField][0]);
        return;
      }
    }
    if (status === 403) { ElMessage.error('您沒有權限執行此動作'); return; }
    if (status === 404) { ElMessage.error('找不到此紀錄'); return; }
  }
  ElMessage.error('操作失敗，請稍後再試');
}
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div class="topbar-left">
        <RouterLink to="/dashboard" class="back-link">← Dashboard</RouterLink>
        <h1>體重紀錄</h1>
      </div>
    </header>

    <p v-if="loading" class="state">載入中…</p>

    <template v-else>
      <!-- 個人資料未完成 → 警告卡 -->
      <el-alert
        v-if="!profileComplete"
        type="warning"
        :closable="false"
        show-icon
        class="alert-profile"
      >
        <template #title>請先完成個人資料設定</template>
        <template #default>
          <p>需要設定身高才能計算 BMI。</p>
          <RouterLink to="/profile" class="btn-go-profile">前往個人資料設定 →</RouterLink>
        </template>
      </el-alert>

      <!-- 新增 / 編輯表單 -->
      <el-card v-if="profileComplete" class="form-card" shadow="never">
        <template #header>
          <div class="card-head">
            <span>{{ editingId ? `編輯體重紀錄（#${editingId}）` : '新增體重紀錄' }}</span>
            <span v-if="!editingId" class="head-meta">
              身高 {{ profile?.height_cm }} cm · 同一天再次儲存會覆蓋
            </span>
          </div>
        </template>

        <el-form :model="form" label-width="84px" @submit.prevent="onSubmit">
          <el-form-item label="日期">
            <el-date-picker
              v-model="form.record_date"
              type="date"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              placeholder="選擇日期"
              style="width: 220px"
            />
          </el-form-item>

          <el-form-item label="體重">
            <el-input-number
              v-model="form.weight_kg"
              :min="20"
              :max="500"
              :step="0.1"
              :precision="1"
              style="width: 220px"
            />
            <span class="unit">kg</span>
          </el-form-item>

          <el-form-item label="備註">
            <el-input
              v-model="form.note"
              type="textarea"
              :rows="2"
              maxlength="500"
              show-word-limit
              placeholder="例：晨間量測、空腹"
            />
          </el-form-item>

          <!-- 階段 G：進階身體量測（折疊） -->
          <div class="advanced-toggle">
            <el-button
              link
              type="primary"
              size="small"
              @click="showAdvanced = !showAdvanced"
            >
              {{ showAdvanced ? '▼' : '▶' }} 進階身體量測（體圍 / 體脂率 / 肌肉量，皆選填）
            </el-button>
            <p class="advanced-hint">
              ⓘ BMI 無法區分肌肉與脂肪，補上這些量測能更準確追蹤身體變化。可選填、不需每次都記。
            </p>
          </div>

          <div v-show="showAdvanced" class="advanced-fields">
            <div class="advanced-grid">
              <el-form-item label="腰圍">
                <el-input-number v-model="form.waist_cm" :min="30" :max="200" :step="0.5" :precision="1" placeholder="例：80" style="width: 100%" />
                <span class="unit">cm</span>
              </el-form-item>
              <el-form-item label="臀圍">
                <el-input-number v-model="form.hip_cm" :min="30" :max="200" :step="0.5" :precision="1" placeholder="例：95" style="width: 100%" />
                <span class="unit">cm</span>
              </el-form-item>
              <el-form-item label="胸圍">
                <el-input-number v-model="form.chest_cm" :min="30" :max="200" :step="0.5" :precision="1" placeholder="例：90" style="width: 100%" />
                <span class="unit">cm</span>
              </el-form-item>
              <el-form-item label="上臂圍">
                <el-input-number v-model="form.arm_cm" :min="10" :max="80" :step="0.5" :precision="1" placeholder="例：32" style="width: 100%" />
                <span class="unit">cm</span>
              </el-form-item>
              <el-form-item label="大腿圍">
                <el-input-number v-model="form.thigh_cm" :min="20" :max="120" :step="0.5" :precision="1" placeholder="例：55" style="width: 100%" />
                <span class="unit">cm</span>
              </el-form-item>
              <el-form-item label="體脂率">
                <el-input-number v-model="form.body_fat_percent" :min="3" :max="60" :step="0.1" :precision="1" placeholder="例：22" style="width: 100%" />
                <span class="unit">%</span>
              </el-form-item>
              <el-form-item label="肌肉量">
                <el-input-number v-model="form.muscle_mass_kg" :min="10" :max="200" :step="0.1" :precision="2" placeholder="例：50" style="width: 100%" />
                <span class="unit">kg</span>
              </el-form-item>
            </div>
          </div>

          <el-form-item>
            <el-button type="primary" :loading="submitting" @click="onSubmit">
              {{ editingId ? '更新' : '儲存' }}
            </el-button>
            <el-button v-if="editingId" @click="cancelEdit">取消編輯</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 體重趨勢 -->
      <el-card class="trend-card" shadow="never">
        <template #header>
          <div class="card-head">
            <span>體重趨勢</span>
            <el-radio-group v-model="trendDays" size="small">
              <el-radio-button :value="7">7 天</el-radio-button>
              <el-radio-button :value="30">30 天</el-radio-button>
              <el-radio-button :value="90">90 天</el-radio-button>
            </el-radio-group>
          </div>
        </template>

        <p v-if="trendLoading" class="trend-state">趨勢載入中…</p>

        <div v-else-if="!trend || trend.records.length === 0" class="trend-state">
          尚未建立體重紀錄，請先新增體重資料。
        </div>

        <template v-else>
          <!-- 修正六：洞察訊息 -->
          <el-alert
            v-if="trend.insights"
            :type="trend.insights.has_sufficient_data ? 'info' : 'warning'"
            :title="trend.insights.message"
            :closable="false"
            show-icon
            class="insights-alert"
          />

          <!-- 階段 F：體重波動解釋（資料足夠時加顯示） -->
          <el-alert
            v-if="fluctuation?.has_enough_data"
            type="info"
            :closable="false"
            show-icon
            class="insights-alert"
          >
            <template #title>{{ fluctuation.message }}</template>
            <template v-if="fluctuation.possible_reasons?.length" #default>
              <div class="reasons-row">
                <span class="reasons-label">可能原因：</span>
                <el-tag
                  v-for="(r, i) in fluctuation.possible_reasons"
                  :key="i"
                  size="small"
                  type="info"
                  effect="light"
                  class="reason-tag"
                >{{ r }}</el-tag>
              </div>
            </template>
          </el-alert>

          <!-- 摘要小卡 -->
          <div class="trend-stats">
            <div class="trend-stat">
              <span class="trend-label">最新體重</span>
              <span class="trend-num">{{ trend.latest_weight_kg !== null ? trend.latest_weight_kg.toFixed(1) : '—' }}</span>
              <span class="trend-unit">kg</span>
            </div>
            <div class="trend-stat">
              <span class="trend-label">7 日平均</span>
              <span class="trend-num">{{ trend.insights?.seven_day_average_kg !== null && trend.insights?.seven_day_average_kg !== undefined ? trend.insights.seven_day_average_kg.toFixed(1) : '—' }}</span>
              <span class="trend-unit">kg</span>
            </div>
            <div class="trend-stat">
              <span class="trend-label">30 日變化</span>
              <span
                class="trend-num"
                :style="{ color: thirtyDayColor() }"
              >{{ thirtyDayText() }}</span>
            </div>
            <div class="trend-stat">
              <span class="trend-label">目標體重</span>
              <span class="trend-num">{{ trend.target_weight_kg !== null ? trend.target_weight_kg.toFixed(1) : '—' }}</span>
              <span class="trend-unit">kg</span>
            </div>
          </div>

          <!-- 內聯 SVG 折線圖 -->
          <svg
            v-if="chart"
            class="chart"
            :viewBox="`0 0 ${chart.W} ${chart.H}`"
            preserveAspectRatio="xMidYMid meet"
          >
            <!-- Y 軸刻度線 + 標籤 -->
            <g v-for="(t, i) in chart.ticks" :key="`tick-${i}`">
              <line
                :x1="chart.PL"
                :y1="t.y"
                :x2="chart.PL + chart.plotW"
                :y2="t.y"
                stroke="#f1f5f9"
                stroke-width="1"
                stroke-dasharray="2,2"
              />
              <text
                :x="chart.PL - 8"
                :y="t.y + 4"
                text-anchor="end"
                font-size="11"
                fill="#94a3b8"
              >{{ t.value.toFixed(1) }}</text>
            </g>

            <!-- Y 軸 + X 軸主線 -->
            <line :x1="chart.PL" :y1="chart.PT" :x2="chart.PL" :y2="chart.PT + chart.plotH" stroke="#cbd5e1" stroke-width="1" />
            <line :x1="chart.PL" :y1="chart.PT + chart.plotH" :x2="chart.PL + chart.plotW" :y2="chart.PT + chart.plotH" stroke="#cbd5e1" stroke-width="1" />

            <!-- 目標體重虛線 + 右側標籤 -->
            <g v-if="chart.targetY !== null && trend.target_weight_kg !== null">
              <line
                :x1="chart.PL"
                :y1="chart.targetY"
                :x2="chart.PL + chart.plotW"
                :y2="chart.targetY"
                stroke="#10b981"
                stroke-width="2"
                stroke-dasharray="6,4"
              />
              <text
                :x="chart.PL + chart.plotW + 4"
                :y="chart.targetY + 4"
                font-size="11"
                fill="#10b981"
              >目標 {{ trend.target_weight_kg.toFixed(1) }}</text>
            </g>

            <!-- 折線 -->
            <path :d="chart.linePath" stroke="#0ea5e9" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />

            <!-- 資料點 + hover 顯示 tooltip（用 native title） -->
            <g v-for="(p, i) in chart.points" :key="`p-${i}`">
              <circle
                :cx="p.x"
                :cy="p.y"
                r="4"
                fill="#0ea5e9"
                stroke="white"
                stroke-width="2"
              >
                <title>{{ p.record_date }} · {{ p.weight_kg.toFixed(1) }} kg · BMI {{ p.bmi.toFixed(2) }}</title>
              </circle>
            </g>

            <!-- X 軸：起訖日期 -->
            <text
              :x="chart.PL"
              :y="chart.PT + chart.plotH + 18"
              font-size="11"
              fill="#94a3b8"
            >{{ chart.points[0].record_date }}</text>
            <text
              v-if="chart.points.length > 1"
              :x="chart.PL + chart.plotW"
              :y="chart.PT + chart.plotH + 18"
              text-anchor="end"
              font-size="11"
              fill="#94a3b8"
            >{{ chart.points[chart.points.length - 1].record_date }}</text>
          </svg>
        </template>
      </el-card>

      <!-- 紀錄列表 -->
      <el-card class="list-card" shadow="never">
        <template #header>
          <div class="card-head">
            <span>紀錄列表</span>
            <span class="head-meta">共 {{ records.length }} 筆</span>
          </div>
        </template>

        <el-empty
          v-if="records.length === 0"
          description="還沒有任何體重紀錄"
        />

        <el-table
          v-else
          :data="records"
          stripe
          style="width: 100%"
        >
          <el-table-column prop="record_date" label="日期" width="120" />
          <el-table-column label="體重" width="110" align="right">
            <template #default="{ row }">
              <span class="num">{{ row.weight_kg.toFixed(1) }}</span> kg
            </template>
          </el-table-column>
          <el-table-column label="BMI" width="100" align="right">
            <template #default="{ row }">
              <span class="num">{{ row.bmi.toFixed(2) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="腰圍" width="80" align="right">
            <template #default="{ row }">
              <span v-if="row.waist_cm !== null" class="num">{{ row.waist_cm.toFixed(1) }}</span>
              <span v-else class="note-empty">—</span>
            </template>
          </el-table-column>
          <el-table-column label="體脂率" width="80" align="right">
            <template #default="{ row }">
              <span v-if="row.body_fat_percent !== null" class="num">{{ row.body_fat_percent.toFixed(1) }}%</span>
              <span v-else class="note-empty">—</span>
            </template>
          </el-table-column>
          <el-table-column label="肌肉量" width="90" align="right">
            <template #default="{ row }">
              <span v-if="row.muscle_mass_kg !== null" class="num">{{ row.muscle_mass_kg.toFixed(1) }}kg</span>
              <span v-else class="note-empty">—</span>
            </template>
          </el-table-column>
          <el-table-column prop="note" label="備註" min-width="120">
            <template #default="{ row }">
              <span v-if="row.note" class="note">{{ row.note }}</span>
              <span v-else class="note-empty">—</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="170" align="center">
            <template #default="{ row }">
              <el-button size="small" @click="onEdit(row)">編輯</el-button>
              <el-button size="small" type="danger" @click="onDelete(row)">刪除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </template>
  </div>
</template>

<style scoped>
.page { max-width: 800px; margin: 32px auto; padding: 0 24px 64px; }

.topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px; }
.topbar-left { display: flex; align-items: baseline; gap: 16px; }
.topbar h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.back-link { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back-link:hover { color: #0ea5e9; }

.state { text-align: center; padding: 40px 0; color: #64748b; }

.alert-profile { margin-bottom: 16px; }
.alert-profile :deep(.el-alert__content) p { margin: 4px 0 8px; }
.btn-go-profile { color: #0ea5e9; text-decoration: none; font-weight: 500; }
.btn-go-profile:hover { text-decoration: underline; }

.form-card, .list-card, .trend-card { margin-bottom: 16px; }

/* 趨勢卡片 */
.trend-state { text-align: center; padding: 24px 0; color: #64748b; }
.insights-alert { margin-bottom: 12px; }
.reasons-row { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-top: 4px; }
.reasons-label { font-size: 0.8125rem; color: #475569; }
.reason-tag { font-size: 0.75rem; }
.trend-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
@media (max-width: 480px) { .trend-stats { grid-template-columns: repeat(2, 1fr); } }
.trend-stat { background: #f8fafc; border-radius: 8px; padding: 10px 12px; text-align: center; }
.trend-label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 4px; }
.trend-num { font-size: 1.25rem; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; }
.trend-unit { margin-left: 2px; color: #64748b; font-size: 0.875rem; }
.chart { width: 100%; height: auto; max-height: 260px; display: block; }
.card-head { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; }
.head-meta { color: #94a3b8; font-size: 0.8125rem; }

.unit { margin-left: 8px; color: #64748b; }

/* 階段 G：進階身體量測 */
.advanced-toggle { margin: 12px 0 8px; padding: 8px 12px; background: #f8fafc; border-radius: 8px; border-left: 3px solid #0ea5e9; }
.advanced-hint { margin: 4px 0 0; font-size: 0.75rem; color: #64748b; line-height: 1.5; }
.advanced-fields { background: #f8fafc; padding: 12px; border-radius: 8px; margin-bottom: 12px; }
.advanced-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px 16px; }
@media (max-width: 600px) { .advanced-grid { grid-template-columns: 1fr; } }
.advanced-grid :deep(.el-form-item) { margin-bottom: 8px; display: flex; align-items: center; }
.advanced-grid :deep(.el-form-item__content) { display: flex; align-items: center; gap: 4px; }

.num { font-variant-numeric: tabular-nums; font-weight: 600; color: #0f172a; }
.note { color: #475569; }
.note-empty { color: #cbd5e1; }
</style>

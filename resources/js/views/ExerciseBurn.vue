<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { profileService } from '@/services/profileService';

interface Exercise {
  name: string;
  emoji: string;
  category: string;
  met: number;
  intensity: '低' | '中' | '高';
  note?: string;
}

// MET 值參考自 Compendium of Physical Activities (Ainsworth 2011)
// 熱量公式：calories = MET × 體重(kg) × 時間(小時)
const EXERCISES: Exercise[] = [
  { name: '散步（慢速）',       emoji: '🚶', category: '日常',   met: 2.5,  intensity: '低', note: '輕鬆散步約 3 km/h' },
  { name: '瑜伽（伸展）',       emoji: '🧘', category: '伸展',   met: 3.0,  intensity: '低' },
  { name: '快走',              emoji: '🚶‍♂️', category: '有氧',   met: 3.5,  intensity: '低', note: '約 5 km/h，輕喘' },
  { name: '桌球',              emoji: '🏓', category: '球類',   met: 4.0,  intensity: '低' },
  { name: '騎腳踏車（休閒）',   emoji: '🚴', category: '有氧',   met: 4.0,  intensity: '低', note: '< 16 km/h 平地' },
  { name: '健走',              emoji: '🥾', category: '有氧',   met: 4.3,  intensity: '中', note: '約 5.5 km/h' },
  { name: '重訓（中等強度）',   emoji: '🏋️', category: '阻力',   met: 5.0,  intensity: '中', note: '組間休息 1 分鐘' },
  { name: '羽球（休閒）',       emoji: '🏸', category: '球類',   met: 5.5,  intensity: '中' },
  { name: '健身操（有氧）',     emoji: '💃', category: '有氧',   met: 6.5,  intensity: '中', note: '韻律操、TRX' },
  { name: '騎腳踏車（中強度）', emoji: '🚴‍♂️', category: '有氧',   met: 6.8,  intensity: '中', note: '16–19 km/h' },
  { name: '游泳（自由式）',     emoji: '🏊', category: '有氧',   met: 7.0,  intensity: '中', note: '中等速度' },
  { name: '拳擊有氧',           emoji: '🥊', category: '有氧',   met: 7.5,  intensity: '高' },
  { name: '慢跑',              emoji: '🏃', category: '有氧',   met: 8.0,  intensity: '中', note: '約 8 km/h' },
  { name: '籃球（比賽）',       emoji: '🏀', category: '球類',   met: 8.0,  intensity: '高' },
  { name: '足球',              emoji: '⚽', category: '球類',   met: 8.5,  intensity: '高' },
  { name: '階梯有氧',           emoji: '📶', category: '有氧',   met: 8.5,  intensity: '高' },
  { name: '跑步',              emoji: '🏃‍♂️', category: '有氧',   met: 10.0, intensity: '高', note: '約 10 km/h' },
  { name: '跳繩（中速）',       emoji: '⏪', category: '有氧',   met: 11.0, intensity: '高', note: '120 下/分' },
  { name: 'HIIT 高強度間歇',    emoji: '🔥', category: '有氧',   met: 12.0, intensity: '高', note: '最有效率燃脂' },
  { name: '飛輪課',             emoji: '🚲', category: '有氧',   met: 12.0, intensity: '高' },
  { name: '游泳（蝶式）',       emoji: '🌊', category: '有氧',   met: 13.8, intensity: '高' },
  { name: '跑步（快）',         emoji: '💨', category: '有氧',   met: 14.0, intensity: '高', note: '約 13 km/h+' },
];

const weight = ref<number>(60);
const duration = ref<number>(30);
const sortBy = ref<'calories' | 'intensity'>('calories');
const filterCategory = ref<string>('all');
const profileLoading = ref<boolean>(true);

onMounted(async () => {
  try {
    const profile = await profileService.getProfile();
    if (profile && profile.weight_kg) {
      weight.value = profile.weight_kg;
    }
  } catch {
    // 沒個人資料就用預設 60kg
  } finally {
    profileLoading.value = false;
  }
});

const categories = computed<string[]>(() => {
  const set = new Set(EXERCISES.map((e) => e.category));
  return ['all', ...Array.from(set)];
});

const filtered = computed<(Exercise & { calories: number })[]>(() => {
  const list = EXERCISES
    .filter((e) => filterCategory.value === 'all' || e.category === filterCategory.value)
    .map((e) => ({
      ...e,
      calories: Math.round(e.met * weight.value * (duration.value / 60)),
    }));
  if (sortBy.value === 'calories') {
    list.sort((a, b) => b.calories - a.calories);
  } else {
    const order: Record<string, number> = { '高': 0, '中': 1, '低': 2 };
    list.sort((a, b) => order[a.intensity] - order[b.intensity]);
  }
  return list;
});

function intensityColor(i: string): string {
  if (i === '高') return '#dc2626';
  if (i === '中') return '#f59e0b';
  return '#10b981';
}

function intensityBg(i: string): string {
  if (i === '高') return '#fef2f2';
  if (i === '中') return '#fffbeb';
  return '#f0fdf4';
}

// 等於多少碗白飯（一碗約 280 kcal）
function bowlsOfRice(cal: number): string {
  const n = cal / 280;
  if (n < 0.3) return '不到 1/3 碗白飯';
  if (n < 0.7) return `約 ${(n).toFixed(1)} 碗白飯`;
  return `約 ${n.toFixed(1)} 碗白飯`;
}
</script>

<template>
  <div class="page">
    <header class="page-header">
      <RouterLink to="/" class="back">← Dashboard</RouterLink>
      <h1>🔥 運動熱量計算</h1>
      <p class="subtitle">查詢各種運動消耗多少卡路里。公式：MET × 體重 × 時間</p>
    </header>

    <div class="card calculator">
      <h2>📐 計算設定</h2>
      <div class="inputs">
        <div class="input-group">
          <label>體重</label>
          <div class="input-with-unit">
            <input
              v-model.number="weight"
              type="number"
              min="30"
              max="200"
              step="0.5"
              :disabled="profileLoading"
            />
            <span class="unit">kg</span>
          </div>
          <small v-if="!profileLoading">已從個人資料自動填入，可手動修改</small>
        </div>
        <div class="input-group">
          <label>運動時間</label>
          <div class="input-with-unit">
            <input
              v-model.number="duration"
              type="number"
              min="5"
              max="180"
              step="5"
            />
            <span class="unit">分鐘</span>
          </div>
          <div class="quick-durations">
            <button v-for="d in [15, 30, 45, 60]" :key="d"
              type="button"
              class="duration-chip"
              :class="{ active: duration === d }"
              @click="duration = d">
              {{ d }} 分
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="filter-row">
      <div class="category-filters">
        <button v-for="c in categories" :key="c"
          type="button"
          class="cat-btn"
          :class="{ active: filterCategory === c }"
          @click="filterCategory = c">
          {{ c === 'all' ? '全部' : c }}
        </button>
      </div>
      <div class="sort-by">
        <label>排序：</label>
        <select v-model="sortBy">
          <option value="calories">依消耗熱量</option>
          <option value="intensity">依運動強度</option>
        </select>
      </div>
    </div>

    <div class="exercise-grid">
      <div v-for="ex in filtered" :key="ex.name" class="exercise-card">
        <div class="exercise-head">
          <span class="emoji">{{ ex.emoji }}</span>
          <div class="exercise-info">
            <h3>{{ ex.name }}</h3>
            <small v-if="ex.note">{{ ex.note }}</small>
          </div>
          <span
            class="intensity-badge"
            :style="{ background: intensityBg(ex.intensity), color: intensityColor(ex.intensity) }"
          >
            {{ ex.intensity }}強度
          </span>
        </div>
        <div class="exercise-stats">
          <div class="cal-stat">
            <span class="cal-num">{{ ex.calories }}</span>
            <span class="cal-unit">kcal</span>
          </div>
          <div class="cal-meta">
            <span class="met-tag">MET {{ ex.met }}</span>
            <span class="rice-tag">≈ {{ bowlsOfRice(ex.calories) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="card disclaimer">
      <h2>⚠️ 重要提醒</h2>
      <ul>
        <li>MET 值來自 Compendium of Physical Activities (Ainsworth 2011)，是<strong>族群平均估算</strong></li>
        <li>實際消耗會受年齡、性別、肌肉量、運動效率影響，誤差可達 ±20%</li>
        <li>「不能用吃的補回來」——靠運動減重效果不如飲食控制</li>
        <li>1 公斤體脂 ≈ 7,700 kcal，跑步 1 小時約消耗 600 kcal，<strong>13 小時跑步才能燒掉 1 公斤體脂</strong>，對比飲食調整少吃 300 kcal × 26 天就能達成</li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.page { max-width: 900px; margin: 24px auto 64px; padding: 0 24px; }

.page-header { margin-bottom: 24px; }
.back { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back:hover { color: #6366f1; }
.page-header h1 {
  margin: 8px 0 4px;
  font-size: 1.875rem;
  background: linear-gradient(135deg, #f59e0b, #ef4444);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  font-weight: 700;
}
.subtitle { color: #64748b; margin: 0; font-size: 0.9375rem; }

.card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 24px;
  margin-bottom: 16px;
}
.card h2 { margin: 0 0 16px; font-size: 1.125rem; color: #0f172a; font-weight: 600; }

/* 計算機 */
.calculator .inputs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.input-group { display: flex; flex-direction: column; gap: 6px; }
.input-group label { font-size: 0.875rem; color: #475569; font-weight: 500; }
.input-with-unit {
  display: flex; align-items: center;
  border: 1px solid #cbd5e1; border-radius: 8px;
  background: white;
  padding-right: 12px;
}
.input-with-unit input {
  flex: 1; border: 0; padding: 10px 12px;
  background: transparent; font-size: 1rem;
  font-variant-numeric: tabular-nums; outline: none;
}
.input-with-unit:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
.unit { font-size: 0.875rem; color: #64748b; }
.input-group small { font-size: 0.75rem; color: #94a3b8; }

.quick-durations { display: flex; gap: 6px; margin-top: 4px; }
.duration-chip {
  padding: 4px 12px;
  background: white;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  font-size: 0.75rem; cursor: pointer;
  color: #475569;
}
.duration-chip:hover { background: #f1f5f9; }
.duration-chip.active { background: #6366f1; color: white; border-color: #6366f1; }

/* 篩選 + 排序 */
.filter-row {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; margin-bottom: 16px; flex-wrap: wrap;
}
.category-filters { display: flex; gap: 6px; flex-wrap: wrap; }
.cat-btn {
  padding: 6px 14px;
  background: white;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  font-size: 0.8125rem;
  color: #475569; cursor: pointer;
}
.cat-btn:hover { background: #f1f5f9; }
.cat-btn.active {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white; border-color: transparent;
}
.sort-by { display: flex; align-items: center; gap: 6px; font-size: 0.875rem; color: #475569; }
.sort-by select {
  padding: 6px 12px; border: 1px solid #cbd5e1;
  border-radius: 8px; background: white; cursor: pointer;
  font-size: 0.875rem;
}

/* 運動卡片格子 */
.exercise-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.exercise-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px 18px;
  transition: all 0.2s;
}
.exercise-card:hover {
  border-color: #c7d2fe;
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(99, 102, 241, 0.10);
}
.exercise-head {
  display: flex; align-items: flex-start; gap: 10px;
  margin-bottom: 12px;
}
.emoji { font-size: 1.75rem; line-height: 1; }
.exercise-info { flex: 1; min-width: 0; }
.exercise-info h3 { margin: 0; font-size: 1rem; color: #0f172a; font-weight: 600; }
.exercise-info small { font-size: 0.75rem; color: #94a3b8; }
.intensity-badge {
  font-size: 0.6875rem;
  padding: 2px 8px;
  border-radius: 999px;
  font-weight: 500;
  flex-shrink: 0;
}

.exercise-stats {
  display: flex; align-items: baseline;
  justify-content: space-between;
  border-top: 1px dashed #e2e8f0;
  padding-top: 10px;
  flex-wrap: wrap; gap: 8px;
}
.cal-stat { display: flex; align-items: baseline; gap: 4px; }
.cal-num {
  font-size: 1.5rem;
  font-weight: 700;
  color: #ef4444;
  font-variant-numeric: tabular-nums;
}
.cal-unit { font-size: 0.8125rem; color: #94a3b8; }
.cal-meta { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.met-tag, .rice-tag {
  font-size: 0.6875rem;
  padding: 2px 8px;
  border-radius: 6px;
  background: #f1f5f9;
  color: #64748b;
}
.met-tag { background: #ede9fe; color: #6d28d9; font-weight: 500; }

/* 免責聲明 */
.disclaimer { background: #fffbeb; border-color: #fef3c7; }
.disclaimer h2 { color: #92400e; }
.disclaimer ul { margin: 0; padding-left: 20px; color: #78350f; font-size: 0.875rem; line-height: 1.7; }
.disclaimer li { margin-bottom: 6px; }

@media (max-width: 480px) {
  .calculator .inputs { grid-template-columns: 1fr; }
  .filter-row { flex-direction: column; align-items: stretch; }
}
</style>

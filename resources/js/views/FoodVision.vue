<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { AxiosError } from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  foodVisionService,
  type VisionAnalyzeResult,
  type VisionCandidate,
} from '@/services/foodVisionService';
import {
  mealService,
  MEAL_TYPE_OPTIONS,
  type MealType,
} from '@/services/mealService';

const router = useRouter();

type State = 'idle' | 'analyzing' | 'analyzed';
const state = ref<State>('idle');

const selectedFile = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const result = ref<VisionAnalyzeResult | null>(null);

const analyzing = ref(false);
const submitting = ref(false);

// 加入餐點的設定
const targetMealType = ref<MealType>('lunch');
const quantityByFood = ref<Record<number, number>>({});

const fileSize = computed(() => {
  if (!selectedFile.value) return '';
  const kb = selectedFile.value.size / 1024;
  if (kb < 1024) return `${kb.toFixed(1)} KB`;
  return `${(kb / 1024).toFixed(2)} MB`;
});

// el-upload 的 :on-change 給的是 UploadFile，包含 .raw
function onFileChange(uploadFile: { raw?: File } | null): void {
  if (!uploadFile?.raw) return;

  const ext = uploadFile.raw.name.toLowerCase().split('.').pop();
  if (!['jpg', 'jpeg', 'png', 'webp'].includes(ext ?? '')) {
    ElMessage.error('照片格式必須是 JPG / JPEG / PNG / WEBP');
    return;
  }

  if (uploadFile.raw.size > 4 * 1024 * 1024) {
    ElMessage.error('照片大小不能超過 4 MB');
    return;
  }

  // 釋放舊的 preview URL
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);

  selectedFile.value = uploadFile.raw;
  previewUrl.value = URL.createObjectURL(uploadFile.raw);
  result.value = null;
  state.value = 'idle';
}

function clearFile(): void {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  selectedFile.value = null;
  previewUrl.value = null;
  result.value = null;
  state.value = 'idle';
}

async function onAnalyze(): Promise<void> {
  if (!selectedFile.value) {
    ElMessage.warning('請先選擇照片');
    return;
  }

  analyzing.value = true;
  state.value = 'analyzing';
  try {
    result.value = await foodVisionService.analyzeFoodPhoto(selectedFile.value);
    state.value = 'analyzed';

    // 預設每個候選食物 quantity = 1
    quantityByFood.value = {};
    for (const c of result.value.candidates) {
      quantityByFood.value[c.id] = 1;
    }

    if (result.value.candidates.length === 0) {
      ElMessage.warning('AI 辨識完成，但沒找到合適的候選食物。請試試手動到食物資料庫搜尋。');
    } else {
      ElMessage.success(`AI 辨識完成，找到 ${result.value.candidates.length} 筆候選食物`);
    }
  } catch (e) {
    handleError(e, '辨識失敗');
    state.value = 'idle';
  } finally {
    analyzing.value = false;
  }
}

async function onAddToMeal(food: VisionCandidate): Promise<void> {
  const qty = quantityByFood.value[food.id] ?? 1;
  if (qty <= 0) {
    ElMessage.warning('份量必須大於 0');
    return;
  }

  // 二次確認
  try {
    await ElMessageBox.confirm(
      `將「${food.name}」 × ${qty} 加入今日「${getMealTypeLabel(targetMealType.value)}」（${Math.round(food.calories * qty)} kcal）。確定？`,
      '加入今日餐點',
      { confirmButtonText: '加入', cancelButtonText: '取消', type: 'info' },
    );
  } catch {
    return;
  }

  submitting.value = true;
  try {
    // 直接建立一筆 meal 含 1 個 item
    const now = new Date();
    const eatenAt = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:00`;

    await mealService.create({
      meal_type: targetMealType.value,
      eaten_at:  eatenAt,
      note:      'AI 拍照辨識加入',
      items: [{ food_id: food.id, quantity: qty }],
    });

    ElMessage.success('已加入今日餐點');
    // 加完跳到飲食紀錄頁讓使用者看
    router.push({ name: 'meals' });
  } catch (e) {
    handleError(e, '加入餐點失敗');
  } finally {
    submitting.value = false;
  }
}

function pad(n: number): string {
  return String(n).padStart(2, '0');
}

function getMealTypeLabel(t: MealType): string {
  return MEAL_TYPE_OPTIONS.find((o) => o.value === t)?.label ?? t;
}

function handleError(e: unknown, fallback: string): void {
  if (e instanceof AxiosError) {
    const msg = e.response?.data?.message as string | undefined;
    const errors = e.response?.data?.errors as Record<string, string[]> | undefined;
    if (errors) {
      const firstField = Object.keys(errors)[0];
      if (firstField && errors[firstField][0]) {
        ElMessage.error(errors[firstField][0]);
        return;
      }
    }
    if (msg) {
      ElMessage.error(msg);
      return;
    }
  }
  ElMessage.error(fallback);
}

const FOOD_CATEGORY_LABEL: Record<string, string> = {
  rice_box: '便當', noodle: '麵店', convenience: '便利商店',
  fast_food: '速食', drink: '飲料', snack: '點心', other: '其他',
};
function categoryLabel(c: string): string {
  return FOOD_CATEGORY_LABEL[c] ?? c;
}
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div class="topbar-left">
        <RouterLink to="/dashboard" class="back-link">← Dashboard</RouterLink>
        <h1>📷 拍照辨識</h1>
      </div>
    </header>

    <!-- 1. 上傳區 -->
    <el-card class="reco-card" shadow="never">
      <template #header>
        <div class="card-head">
          <span>選擇食物照片</span>
          <span class="card-meta">JPG / PNG / WEBP，最大 4 MB</span>
        </div>
      </template>

      <el-upload
        v-if="!selectedFile"
        drag
        :auto-upload="false"
        :show-file-list="false"
        :on-change="onFileChange"
        accept=".jpg,.jpeg,.png,.webp"
        class="upload-zone"
      >
        <div class="upload-content">
          <span class="upload-icon">📸</span>
          <div class="upload-text">將食物照片拖放到這裡，或<em>點擊選擇</em></div>
        </div>
        <template #tip>
          <div class="upload-tip">建議拍清楚、光線足夠的食物正面照。AI 辨識僅供參考，準確度會因食物種類有限。</div>
        </template>
      </el-upload>

      <!-- 已選照片預覽 -->
      <div v-else class="preview">
        <img :src="previewUrl ?? ''" alt="預覽" class="preview-img" />
        <div class="preview-info">
          <p class="preview-name">📄 {{ selectedFile.name }}</p>
          <p class="preview-size">{{ fileSize }}</p>
          <div class="preview-actions">
            <el-button
              type="primary"
              :loading="analyzing"
              @click="onAnalyze"
            >
              {{ state === 'analyzed' ? '重新辨識' : '開始辨識' }}
            </el-button>
            <el-button @click="clearFile">換一張</el-button>
          </div>
        </div>
      </div>
    </el-card>

    <!-- 2. 辨識結果（state = analyzed） -->
    <template v-if="state === 'analyzed' && result">
      <!-- AI 看到什麼 -->
      <el-card class="reco-card" shadow="never">
        <template #header>
          <span>AI 看到的內容</span>
        </template>
        <div v-if="result.labels.length === 0 && result.entities.length === 0" class="state">
          AI 沒有抓到清楚的食物標籤
        </div>
        <div v-else>
          <div v-if="result.labels.length > 0" class="tag-row">
            <span class="tag-label">標籤：</span>
            <el-tag
              v-for="(l, i) in result.labels"
              :key="`l-${i}`"
              size="small"
              effect="light"
              class="vision-tag"
            >
              {{ l.name }} <span class="score">{{ (l.score * 100).toFixed(0) }}%</span>
            </el-tag>
          </div>
          <div v-if="result.entities.length > 0" class="tag-row">
            <span class="tag-label">相關詞：</span>
            <el-tag
              v-for="(e, i) in result.entities"
              :key="`e-${i}`"
              size="small"
              type="success"
              effect="plain"
              class="vision-tag"
            >
              {{ e.name }}
            </el-tag>
          </div>
        </div>
      </el-card>

      <!-- 候選食物（從 foods 資料庫比對出來）-->
      <el-card class="reco-card" shadow="never">
        <template #header>
          <div class="card-head">
            <span>候選食物（從資料庫比對）</span>
            <span class="card-meta">{{ result.candidates.length }} 筆</span>
          </div>
        </template>

        <div v-if="result.candidates.length === 0" class="empty-candidates">
          <p>沒有合適的候選食物，可能是：</p>
          <ul>
            <li>AI 辨識結果跟你的食物資料庫沒有匹配</li>
            <li>該食物還沒在資料庫中</li>
          </ul>
          <RouterLink to="/foods/new">
            <el-button type="primary" size="small">手動新增此食物</el-button>
          </RouterLink>
          <RouterLink to="/foods">
            <el-button size="small">手動搜尋食物資料庫</el-button>
          </RouterLink>
        </div>

        <div v-else>
          <!-- 加入時要選的餐別 -->
          <div class="meal-type-row">
            <span class="meal-type-label">加入今日：</span>
            <el-radio-group v-model="targetMealType" size="small">
              <el-radio-button
                v-for="opt in MEAL_TYPE_OPTIONS"
                :key="opt.value"
                :value="opt.value"
              >{{ opt.icon }} {{ opt.label }}</el-radio-button>
            </el-radio-group>
          </div>

          <!-- 候選列表 -->
          <div class="candidates">
            <article
              v-for="food in result.candidates"
              :key="food.id"
              class="cand"
            >
              <header class="cand-head">
                <span class="cand-name">{{ food.name }}</span>
                <el-tag size="small" type="info" effect="plain">
                  {{ categoryLabel(food.category) }}
                </el-tag>
                <span class="match-score">配對 {{ Math.round(food.match_score * 100) }}%</span>
              </header>
              <p v-if="food.brand" class="cand-brand">{{ food.brand }}</p>
              <p class="cand-serving">每 {{ food.serving_size }} {{ food.serving_unit }} · {{ food.calories }} kcal</p>
              <dl class="cand-macros">
                <div><dt>蛋白</dt><dd>{{ food.protein_g }}g</dd></div>
                <div><dt>脂肪</dt><dd>{{ food.fat_g }}g</dd></div>
                <div><dt>碳水</dt><dd>{{ food.carbs_g }}g</dd></div>
              </dl>
              <div class="cand-add">
                <span class="qty-label">份量：</span>
                <el-input-number
                  v-model="quantityByFood[food.id]"
                  :min="0.1"
                  :max="99"
                  :step="0.5"
                  :precision="1"
                  size="small"
                  controls-position="right"
                  style="width: 100px"
                />
                <span class="cand-cal">≈ {{ Math.round(food.calories * (quantityByFood[food.id] ?? 1)) }} kcal</span>
                <el-button
                  type="primary"
                  size="small"
                  :loading="submitting"
                  @click="onAddToMeal(food)"
                >
                  加入餐點
                </el-button>
              </div>
            </article>
          </div>
        </div>
      </el-card>

      <!-- 注意事項 -->
      <el-alert
        v-for="(note, i) in result.notes"
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
.page { max-width: 880px; margin: 32px auto; padding: 0 24px 64px; }

.topbar { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px; }
.topbar-left { display: flex; align-items: baseline; gap: 16px; }
.topbar h1 { margin: 0; font-size: 1.5rem; color: #0f172a; }
.back-link { color: #64748b; font-size: 0.875rem; text-decoration: none; }
.back-link:hover { color: #0ea5e9; }

.state { text-align: center; padding: 24px 0; color: #64748b; font-size: 0.9375rem; }

.reco-card { margin-bottom: 16px; }
.card-head { display: flex; justify-content: space-between; align-items: baseline; }
.card-meta { color: #94a3b8; font-size: 0.8125rem; }

/* 上傳區 */
.upload-zone :deep(.el-upload-dragger) { padding: 28px 24px; border-radius: 10px; }
.upload-content { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.upload-icon { font-size: 2.5rem; }
.upload-text { color: #475569; font-size: 0.9375rem; }
.upload-text em { color: #0ea5e9; font-style: normal; font-weight: 600; }
.upload-tip { color: #94a3b8; font-size: 0.8125rem; margin-top: 8px; text-align: center; }

/* 預覽 */
.preview { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
.preview-img { width: 220px; height: 220px; object-fit: cover; border-radius: 10px; border: 1px solid #e2e8f0; }
.preview-info { flex: 1; min-width: 200px; }
.preview-name { margin: 0 0 4px; font-size: 0.9375rem; color: #0f172a; word-break: break-all; }
.preview-size { margin: 0 0 12px; font-size: 0.8125rem; color: #64748b; }
.preview-actions { display: flex; gap: 8px; }

/* 標籤區 */
.tag-row { margin-bottom: 12px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.tag-label { font-size: 0.8125rem; color: #475569; min-width: 60px; }
.vision-tag { font-size: 0.8125rem; }
.score { color: #94a3b8; font-size: 0.6875rem; margin-left: 4px; }

/* 沒候選的 fallback */
.empty-candidates { text-align: center; padding: 16px 0; color: #475569; }
.empty-candidates ul { display: inline-block; text-align: left; margin: 8px 0 16px; color: #64748b; font-size: 0.875rem; }
.empty-candidates :deep(.el-button) { margin: 4px; }

/* 候選列表 */
.meal-type-row { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding: 10px; background: #f0f9ff; border-radius: 8px; flex-wrap: wrap; }
.meal-type-label { font-size: 0.875rem; color: #0369a1; font-weight: 600; }

.candidates { display: flex; flex-direction: column; gap: 12px; }
.cand { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; }
.cand-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px; }
.cand-name { font-size: 1rem; font-weight: 600; color: #0f172a; }
.match-score { margin-left: auto; font-size: 0.75rem; color: #0ea5e9; font-weight: 600; }
.cand-brand, .cand-serving { margin: 2px 0; font-size: 0.8125rem; color: #64748b; }
.cand-macros { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin: 8px 0; max-width: 240px; }
.cand-macros > div { text-align: center; }
.cand-macros dt { font-size: 0.6875rem; color: #94a3b8; margin: 0; }
.cand-macros dd { margin: 2px 0 0; font-size: 0.8125rem; font-weight: 600; color: #1f2937; }
.cand-add { display: flex; align-items: center; gap: 8px; padding-top: 8px; border-top: 1px dashed #f1f5f9; flex-wrap: wrap; }
.qty-label { font-size: 0.8125rem; color: #475569; }
.cand-cal { font-size: 0.875rem; color: #0ea5e9; font-weight: 600; }

.note-alert { margin-top: 8px; }
</style>

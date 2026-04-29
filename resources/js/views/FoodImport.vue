<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { AxiosError } from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  foodImportService,
  type FoodImportPreview,
  type FoodImportResult,
} from '@/services/foodImportService';

const router = useRouter();

type ImportState = 'idle' | 'previewed' | 'imported';
const state = ref<ImportState>('idle');

const selectedFile = ref<File | null>(null);
const previewData = ref<FoodImportPreview | null>(null);
const importResult = ref<FoodImportResult | null>(null);

const previewing = ref(false);
const importing = ref(false);

const fileSize = computed(() => {
  if (!selectedFile.value) return '';
  const kb = selectedFile.value.size / 1024;
  if (kb < 1024) return `${kb.toFixed(1)} KB`;
  return `${(kb / 1024).toFixed(2)} MB`;
});

// el-upload 的 :on-change 會傳 UploadFile 物件
function onFileChange(uploadFile: { raw?: File } | null): void {
  if (!uploadFile?.raw) return;

  // 檔案類型基本檢查（mimes 由後端嚴格驗）
  const ext = uploadFile.raw.name.toLowerCase().split('.').pop();
  if (!['csv', 'txt', 'json'].includes(ext ?? '')) {
    ElMessage.error('檔案格式必須是 CSV、TXT 或 JSON');
    return;
  }

  // 大小檢查（2 MB）
  if (uploadFile.raw.size > 2 * 1024 * 1024) {
    ElMessage.error('檔案大小不能超過 2 MB');
    return;
  }

  selectedFile.value = uploadFile.raw;
  // 換新檔 → 清掉之前的結果
  previewData.value = null;
  importResult.value = null;
  state.value = 'idle';
}

function clearFile(): void {
  selectedFile.value = null;
  previewData.value = null;
  importResult.value = null;
  state.value = 'idle';
}

async function onPreview(): Promise<void> {
  if (!selectedFile.value) {
    ElMessage.warning('請先選擇檔案');
    return;
  }

  previewing.value = true;
  importResult.value = null;
  try {
    previewData.value = await foodImportService.previewFoodImport(selectedFile.value);
    state.value = 'previewed';

    if (previewData.value.valid_count === 0) {
      ElMessage.warning('沒有可匯入的資料，請檢查無效列的錯誤訊息');
    } else {
      ElMessage.success(`預覽完成，有效 ${previewData.value.valid_count} 筆 / 無效 ${previewData.value.invalid_count} 筆`);
    }
  } catch (e) {
    handleError(e, '預覽失敗');
  } finally {
    previewing.value = false;
  }
}

async function onImport(): Promise<void> {
  if (!selectedFile.value || !previewData.value) {
    ElMessage.warning('請先預覽檔案');
    return;
  }
  if (previewData.value.valid_count === 0) {
    ElMessage.warning('沒有可匯入的資料');
    return;
  }

  // 確認對話框
  try {
    await ElMessageBox.confirm(
      `將匯入 ${previewData.value.valid_count} 筆有效資料（${previewData.value.invalid_count} 筆無效會被略過）。確定要繼續嗎？`,
      '確認匯入',
      {
        confirmButtonText: '確認匯入',
        cancelButtonText: '取消',
        type: 'info',
      },
    );
  } catch {
    return; // 取消
  }

  importing.value = true;
  try {
    const res = await foodImportService.importFoods(selectedFile.value);
    importResult.value = res.data;
    state.value = 'imported';
    ElMessage.success(res.message);
  } catch (e) {
    handleError(e, '匯入失敗');
  } finally {
    importing.value = false;
  }
}

function reset(): void {
  selectedFile.value = null;
  previewData.value = null;
  importResult.value = null;
  state.value = 'idle';
}

function goToFoods(): void {
  router.push({ name: 'foods' });
}

function handleError(e: unknown, fallback: string): void {
  if (e instanceof AxiosError) {
    const status = e.response?.status;
    const errors = e.response?.data?.errors as Record<string, string[]> | undefined;
    const msg = e.response?.data?.message as string | undefined;

    if (status === 422 && errors) {
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

// 把 errors 陣列轉成顯示字串
function joinErrors(errors: string[]): string {
  return errors.join('、');
}

// 中文分類顯示
const CATEGORY_LABEL: Record<string, string> = {
  rice_box: '便當',
  noodle: '麵店',
  convenience: '便利商店',
  fast_food: '速食',
  drink: '飲料',
  snack: '點心',
  other: '其他',
};
function categoryLabel(c: string): string {
  return CATEGORY_LABEL[c] ?? c;
}
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div class="topbar-left">
        <RouterLink to="/foods" class="back-link">← 食物資料庫</RouterLink>
        <h1>匯入食物資料</h1>
      </div>
    </header>

    <!-- 1. 上傳區 -->
    <el-card class="up-card" shadow="never">
      <template #header>
        <span>選擇檔案</span>
      </template>

      <el-upload
        drag
        :auto-upload="false"
        :show-file-list="false"
        :on-change="onFileChange"
        accept=".csv,.txt,.json"
        class="upload-zone"
      >
        <div class="upload-content">
          <span class="upload-icon">📥</span>
          <div class="upload-text">將檔案拖放到這裡，或<em>點擊選擇</em></div>
        </div>
        <template #tip>
          <div class="upload-tip">支援 CSV / JSON 格式，最大 2 MB · 同名 + 同品牌的食物會被標為失敗</div>
          <div class="upload-tip">匯入後資料一律標示為「來源：匯入 / 可信度：低」，以提醒外部資料未經官方驗證</div>
        </template>
      </el-upload>

      <div v-if="selectedFile" class="file-info">
        <span class="file-name">📄 {{ selectedFile.name }}</span>
        <span class="file-size">{{ fileSize }}</span>
        <el-button size="small" @click="clearFile">移除</el-button>
      </div>

      <div class="up-actions">
        <el-button
          type="primary"
          :loading="previewing"
          :disabled="!selectedFile"
          @click="onPreview"
        >
          {{ state === 'previewed' || state === 'imported' ? '重新預覽' : '預覽匯入' }}
        </el-button>
        <el-button v-if="state !== 'idle'" @click="reset">清除</el-button>
      </div>
    </el-card>

    <!-- 2. 預覽結果 -->
    <template v-if="(state === 'previewed' || state === 'imported') && previewData">
      <el-card class="preview-card" shadow="never">
        <template #header>
          <div class="card-head">
            <span>預覽結果</span>
            <span class="card-meta">總 {{ previewData.total_rows }} 筆</span>
          </div>
        </template>

        <div class="stat-grid">
          <div class="stat">
            <span class="stat-label">總筆數</span>
            <span class="stat-num">{{ previewData.total_rows }}</span>
          </div>
          <div class="stat">
            <span class="stat-label">有效</span>
            <span class="stat-num" style="color: #10b981;">{{ previewData.valid_count }}</span>
          </div>
          <div class="stat">
            <span class="stat-label">無效</span>
            <span class="stat-num" style="color: #dc2626;">{{ previewData.invalid_count }}</span>
          </div>
          <div class="stat">
            <span class="stat-label">有效率</span>
            <span class="stat-num">
              {{ previewData.total_rows > 0
                ? Math.round(previewData.valid_count / previewData.total_rows * 100)
                : 0 }}%
            </span>
          </div>
        </div>

        <!-- 有效列表 -->
        <h3 class="section-title">✓ 有效資料（{{ previewData.valid_count }} 筆）</h3>
        <el-empty v-if="previewData.valid_count === 0" description="沒有有效的資料列" :image-size="60" />
        <el-table v-else :data="previewData.valid_rows" stripe size="small" :max-height="300">
          <el-table-column prop="row_number" label="第幾列" width="80" align="center" />
          <el-table-column label="名稱" min-width="140">
            <template #default="{ row }">{{ row.data.name }}</template>
          </el-table-column>
          <el-table-column label="品牌" min-width="100">
            <template #default="{ row }">{{ row.data.brand ?? '—' }}</template>
          </el-table-column>
          <el-table-column label="分類" width="100">
            <template #default="{ row }">{{ categoryLabel(row.data.category) }}</template>
          </el-table-column>
          <el-table-column label="份量" width="100" align="right">
            <template #default="{ row }">{{ row.data.serving_size }} {{ row.data.serving_unit }}</template>
          </el-table-column>
          <el-table-column label="熱量" width="100" align="right">
            <template #default="{ row }">{{ row.data.calories }} kcal</template>
          </el-table-column>
          <el-table-column label="P / F / C (g)" min-width="140" align="right">
            <template #default="{ row }">
              {{ row.data.protein_g ?? 0 }} / {{ row.data.fat_g ?? 0 }} / {{ row.data.carbs_g ?? 0 }}
            </template>
          </el-table-column>
        </el-table>

        <!-- 無效列表 -->
        <h3 v-if="previewData.invalid_count > 0" class="section-title">✗ 無效資料（{{ previewData.invalid_count }} 筆）</h3>
        <el-table
          v-if="previewData.invalid_count > 0"
          :data="previewData.invalid_rows"
          stripe
          size="small"
          :max-height="300"
          class="invalid-table"
        >
          <el-table-column prop="row_number" label="第幾列" width="80" align="center" />
          <el-table-column label="原始資料" min-width="220">
            <template #default="{ row }">
              <span class="raw-data">
                name="{{ row.data.name ?? '' }}",
                brand="{{ row.data.brand ?? '' }}",
                category="{{ row.data.category ?? '' }}"
              </span>
            </template>
          </el-table-column>
          <el-table-column label="錯誤原因" min-width="280">
            <template #default="{ row }">
              <span class="errors-text">{{ joinErrors(row.errors) }}</span>
            </template>
          </el-table-column>
        </el-table>

        <!-- 確認匯入按鈕 -->
        <div v-if="state === 'previewed'" class="actions">
          <el-button
            type="primary"
            :loading="importing"
            :disabled="previewData.valid_count === 0"
            @click="onImport"
          >
            確認匯入（{{ previewData.valid_count }} 筆）
          </el-button>
        </div>
      </el-card>
    </template>

    <!-- 3. 匯入結果 -->
    <template v-if="state === 'imported' && importResult">
      <el-alert
        v-if="importResult.imported_count > 0"
        type="success"
        :title="`成功匯入 ${importResult.imported_count} 筆食物`"
        show-icon
        :closable="false"
        class="result-alert"
      />
      <el-alert
        v-if="importResult.failed_count > 0"
        type="warning"
        :title="`${importResult.failed_count} 筆無法匯入（已略過）`"
        show-icon
        :closable="false"
        class="result-alert"
      />

      <el-card v-if="importResult.imported_count > 0" class="result-card" shadow="never">
        <template #header>
          <span>匯入成功的食物</span>
        </template>
        <el-table :data="importResult.imported_foods" stripe size="small" :max-height="300">
          <el-table-column prop="id" label="ID" width="70" align="center" />
          <el-table-column prop="name" label="名稱" min-width="140" />
          <el-table-column label="品牌" min-width="100">
            <template #default="{ row }">{{ row.brand ?? '—' }}</template>
          </el-table-column>
          <el-table-column label="分類" width="100">
            <template #default="{ row }">{{ categoryLabel(row.category) }}</template>
          </el-table-column>
          <el-table-column label="熱量" width="100" align="right">
            <template #default="{ row }">{{ row.calories }} kcal</template>
          </el-table-column>
        </el-table>
      </el-card>

      <el-card v-if="importResult.failed_count > 0" class="result-card" shadow="never">
        <template #header>
          <span>匯入失敗的列</span>
        </template>
        <el-table :data="importResult.failed_rows" stripe size="small" :max-height="300">
          <el-table-column prop="row_number" label="第幾列" width="80" align="center" />
          <el-table-column label="錯誤原因" min-width="280">
            <template #default="{ row }">
              <span class="errors-text">{{ joinErrors(row.errors) }}</span>
            </template>
          </el-table-column>
        </el-table>
      </el-card>

      <div class="actions">
        <el-button type="primary" @click="goToFoods">前往食物資料庫</el-button>
        <el-button @click="reset">再匯入一批</el-button>
      </div>
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

.up-card, .preview-card, .result-card { margin-bottom: 16px; }
.card-head { display: flex; justify-content: space-between; align-items: baseline; }
.card-meta { color: #94a3b8; font-size: 0.8125rem; }

/* 上傳區 */
.upload-zone :deep(.el-upload-dragger) { padding: 28px 24px; border-radius: 10px; }
.upload-content { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.upload-icon { font-size: 2.5rem; }
.upload-text { color: #475569; font-size: 0.9375rem; }
.upload-text em { color: #0ea5e9; font-style: normal; font-weight: 600; }
.upload-tip { color: #94a3b8; font-size: 0.8125rem; margin-top: 8px; text-align: center; }

.file-info { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: #f0f9ff; border-radius: 8px; margin-top: 12px; }
.file-name { flex: 1; color: #0f172a; font-size: 0.9375rem; }
.file-size { color: #64748b; font-size: 0.8125rem; font-variant-numeric: tabular-nums; }

.up-actions, .actions { display: flex; gap: 8px; margin-top: 12px; }

/* 預覽統計 */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
@media (max-width: 600px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
.stat { background: #f8fafc; border-radius: 8px; padding: 12px; text-align: center; display: flex; flex-direction: column; gap: 2px; }
.stat-label { font-size: 0.75rem; color: #94a3b8; }
.stat-num { font-size: 1.5rem; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; }

.section-title { margin: 16px 0 8px; font-size: 0.9375rem; color: #334155; }

.invalid-table :deep(.el-table__row) { background: #fef2f2; }

.raw-data { font-family: ui-monospace, monospace; font-size: 0.8125rem; color: #475569; }
.errors-text { color: #b91c1c; font-size: 0.875rem; }

.result-alert { margin-bottom: 12px; }
</style>

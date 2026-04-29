import http from './http';

// === 共用型別 ===

export type FoodCategory =
  | 'rice_box' | 'noodle' | 'convenience'
  | 'fast_food' | 'drink' | 'snack' | 'other';

export interface NormalizedFoodRow {
  name: string;
  brand: string | null;
  category: FoodCategory;
  serving_unit: string;
  serving_size: number;
  calories: number;
  protein_g: number | null;
  fat_g: number | null;
  carbs_g: number | null;
}

export interface ValidImportRow {
  row_number: number;
  data: NormalizedFoodRow;
}

export interface InvalidImportRow {
  row_number: number;
  data: Record<string, unknown>;  // 原始資料（給人看）
  errors: string[];
}

export interface FoodImportPreview {
  total_rows: number;
  valid_count: number;
  invalid_count: number;
  valid_rows: ValidImportRow[];
  invalid_rows: InvalidImportRow[];
}

export interface ImportedFood {
  id: number;
  name: string;
  brand: string | null;
  category: FoodCategory;
  calories: number;
}

export interface FailedImportRow {
  row_number: number;
  errors: string[];
}

export interface FoodImportResult {
  total_rows: number;
  imported_count: number;
  failed_count: number;
  imported_foods: ImportedFood[];
  failed_rows: FailedImportRow[];
}

export interface FoodImportResponse {
  data: FoodImportResult;
  message: string;
}

interface FoodImportPreviewResponse {
  data: FoodImportPreview;
}

/**
 * 食物匯入 API。
 * 透過 multipart/form-data 上傳檔案。
 */
export const foodImportService = {
  /** POST /api/foods/import/preview — 只預覽不寫入 */
  previewFoodImport(file: File): Promise<FoodImportPreview> {
    const fd = new FormData();
    fd.append('file', file);
    return http
      .post<FoodImportPreviewResponse>('/foods/import/preview', fd)
      .then((r) => r.data.data);
  },

  /** POST /api/foods/import — 正式匯入（valid rows 寫入 DB） */
  importFoods(file: File): Promise<FoodImportResponse> {
    const fd = new FormData();
    fd.append('file', file);
    return http
      .post<FoodImportResponse>('/foods/import', fd)
      .then((r) => r.data);
  },
};

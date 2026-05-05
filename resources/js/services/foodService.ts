import http from './http';

export type FoodCategory =
  | 'rice_box'
  | 'noodle'
  | 'convenience'
  | 'fast_food'
  | 'drink'
  | 'snack'
  | 'other';

export type FoodSourceType =
  | 'system_estimate'   // 系統內建估算
  | 'user_custom'        // 使用者手動建立
  | 'imported'           // CSV / JSON 匯入
  | 'ai_estimate'        // AI 估算（Gemini）
  | 'official';          // 官方資料（保留欄位、目前未使用）

export type FoodConfidenceLevel = 'high' | 'medium' | 'low';

export interface Food {
  id: number;
  name: string;
  brand: string | null;
  category: FoodCategory;
  serving_unit: string;
  serving_size: number;
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
  is_system: boolean;
  created_by_user_id: number | null;
  // 修正四：資料來源與可信度
  source_type: FoodSourceType;
  confidence_level: FoodConfidenceLevel;
  is_owned: boolean;
  created_at: string | null;
  updated_at: string | null;
}

/** 來源中文標籤 */
export const SOURCE_LABEL: Record<FoodSourceType, string> = {
  system_estimate: '系統估算',
  user_custom:     '我的',
  imported:        '匯入',
  ai_estimate:     'AI 估算',
  official:        '✓ 衛福部',
};

/** 可信度中文標籤 */
export const CONFIDENCE_LABEL: Record<FoodConfidenceLevel, string> = {
  high:   '高',
  medium: '中',
  low:    '低',
};

export interface FoodListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface FoodListResponse {
  data: Food[];
  meta: FoodListMeta;
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

export interface FoodSearchParams {
  search?: string;
  category?: FoodCategory | '';
  page?: number;
  per_page?: number;
}

export interface FoodPayload {
  name: string;
  brand: string | null;
  category: FoodCategory;
  serving_unit: string;
  serving_size: number;
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
  /** 連到哪一家店家。null = 不屬於任何店 */
  store_id?: number | null;
}

/**
 * 食物 CRUD 與搜尋。
 * Vue component 不要直接呼叫 axios。
 */
export const foodService = {
  list(params: FoodSearchParams = {}): Promise<FoodListResponse> {
    // 把空字串拿掉，避免送 ?search=&category= 這種雜訊
    const cleaned: Record<string, string | number> = {};
    if (params.search) cleaned.search = params.search;
    if (params.category) cleaned.category = params.category;
    if (params.page) cleaned.page = params.page;
    if (params.per_page) cleaned.per_page = params.per_page;
    return http.get<FoodListResponse>('/foods', { params: cleaned }).then((r) => r.data);
  },

  show(id: number): Promise<Food> {
    return http.get<{ food: Food }>(`/foods/${id}`).then((r) => r.data.food);
  },

  create(payload: FoodPayload): Promise<Food> {
    return http.post<{ food: Food }>('/foods', payload).then((r) => r.data.food);
  },

  update(id: number, payload: FoodPayload): Promise<Food> {
    return http.put<{ food: Food }>(`/foods/${id}`, payload).then((r) => r.data.food);
  },

  delete(id: number): Promise<void> {
    return http.delete(`/foods/${id}`).then(() => undefined);
  },
};

/**
 * UI 用的常數 — 類別下拉選單與顯示對照
 */
export const CATEGORY_OPTIONS: Array<{ value: FoodCategory; label: string }> = [
  { value: 'rice_box',    label: '便當' },
  { value: 'noodle',      label: '麵店' },
  { value: 'convenience', label: '便利商店' },
  { value: 'fast_food',   label: '速食' },
  { value: 'drink',       label: '飲料' },
  { value: 'snack',       label: '點心' },
  { value: 'other',       label: '其他' },
];

export const CATEGORY_LABEL: Record<FoodCategory, string> = {
  rice_box:    '便當',
  noodle:      '麵店',
  convenience: '便利商店',
  fast_food:   '速食',
  drink:       '飲料',
  snack:       '點心',
  other:       '其他',
};


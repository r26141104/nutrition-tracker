import http from './http';

export type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snack';

export interface FoodSummary {
  id: number;
  name: string;
  brand: string | null;
  category: string;
  serving_unit: string;
  serving_size: number;
}

export interface MealItemSnapshot {
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
}

export interface MealItem {
  id: number;
  meal_id: number;
  food_id: number | null;
  food_summary: FoodSummary | null; // 食物被刪 → null
  quantity: number;
  snapshot: MealItemSnapshot; // 每 1 單位的當下值
  total: MealItemSnapshot;    // snapshot × quantity
  created_at: string | null;
  updated_at: string | null;
}

export interface MealTotals {
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
}

export interface Meal {
  id: number;
  user_id: number;
  eaten_at: string; // ISO 8601
  meal_type: MealType;
  note: string | null;
  items: MealItem[];
  totals: MealTotals;
  item_count: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface MealItemPayload {
  food_id: number;
  quantity: number;
}

export interface MealPayload {
  eaten_at: string; // 'YYYY-MM-DD HH:mm:ss' 或 ISO
  meal_type: MealType;
  note?: string | null;
  items?: MealItemPayload[]; // update 時不傳 = 不動 items；傳了 = 整批替換
}

export interface DailySummaryBucket {
  meal_count: number;
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
}

export interface DailySummary {
  date: string;
  totals: MealTotals;
  by_meal_type: Record<MealType, DailySummaryBucket>;
  meal_count: number;
}

interface MealListResponse {
  data: Meal[];
}

interface MealResponse {
  meal: Meal;
}

interface MealItemResponse {
  item: MealItem;
}

/**
 * 飲食紀錄 CRUD。
 * Vue component 不要直接呼叫 axios — 一律走這個 service。
 */
export const mealService = {
  /**
   * GET /api/meals?date=YYYY-MM-DD&meal_type=lunch
   * 回傳當日所有 meals（含 items + food_summary）。
   */
  list(params: { date?: string; meal_type?: MealType } = {}): Promise<Meal[]> {
    const cleaned: Record<string, string> = {};
    if (params.date) cleaned.date = params.date;
    if (params.meal_type) cleaned.meal_type = params.meal_type;
    return http
      .get<MealListResponse>('/meals', { params: cleaned })
      .then((r) => r.data.data);
  },

  show(id: number): Promise<Meal> {
    return http.get<MealResponse>(`/meals/${id}`).then((r) => r.data.meal);
  },

  create(payload: MealPayload): Promise<Meal> {
    return http.post<MealResponse>('/meals', payload).then((r) => r.data.meal);
  },

  update(id: number, payload: MealPayload): Promise<Meal> {
    return http.put<MealResponse>(`/meals/${id}`, payload).then((r) => r.data.meal);
  },

  delete(id: number): Promise<void> {
    return http.delete(`/meals/${id}`).then(() => undefined);
  },

  addItem(mealId: number, payload: MealItemPayload): Promise<MealItem> {
    return http
      .post<MealItemResponse>(`/meals/${mealId}/items`, payload)
      .then((r) => r.data.item);
  },

  updateItem(mealId: number, itemId: number, payload: MealItemPayload): Promise<MealItem> {
    return http
      .put<MealItemResponse>(`/meals/${mealId}/items/${itemId}`, payload)
      .then((r) => r.data.item);
  },

  deleteItem(mealId: number, itemId: number): Promise<void> {
    return http.delete(`/meals/${mealId}/items/${itemId}`).then(() => undefined);
  },

  dailySummary(date?: string): Promise<DailySummary> {
    const params: Record<string, string> = {};
    if (date) params.date = date;
    return http.get<DailySummary>('/meals/daily-summary', { params }).then((r) => r.data);
  },
};

// === UI 常數 ===

export const MEAL_TYPE_OPTIONS: Array<{ value: MealType; label: string; icon: string }> = [
  { value: 'breakfast', label: '早餐', icon: '☀️' },
  { value: 'lunch',     label: '午餐', icon: '🍱' },
  { value: 'dinner',    label: '晚餐', icon: '🍲' },
  { value: 'snack',     label: '點心', icon: '🍪' },
];

export const MEAL_TYPE_LABEL: Record<MealType, string> = {
  breakfast: '早餐',
  lunch:     '午餐',
  dinner:    '晚餐',
  snack:     '點心',
};

export const MEAL_TYPE_ICON: Record<MealType, string> = {
  breakfast: '☀️',
  lunch:     '🍱',
  dinner:    '🍲',
  snack:     '🍪',
};

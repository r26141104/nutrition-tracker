import http from './http';

export type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snack';

export interface Nutrients {
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
}

export interface NutritionTargetSummary {
  target_weight_kg: number;
  bmr: number;
  tdee: number;
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
}

export interface ProgressPercent {
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
}

export interface IsOver {
  calories: boolean;
  protein_g: boolean;
  fat_g: boolean;
  carbs_g: boolean;
}

export type WarningType = 'info' | 'warning' | 'danger';

export type WarningCategory =
  | 'general'
  | 'target_bmi'
  | 'calories'
  | 'protein'
  | 'fat'
  | 'carbs'
  | 'weight';

export interface WarningItem {
  type: WarningType;
  category: WarningCategory;
  message: string;
}

export interface DashboardItem {
  id: number;
  food_name: string;
  quantity: number;
  // 注意：以下都是「該 item 已乘上 quantity 的累計值」
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
}

export interface DashboardMeal {
  id: number;
  meal_date: string;        // 'YYYY-MM-DD'
  eaten_at: string;         // ISO 8601
  meal_type: MealType;
  meal_type_label: string;  // '早餐' / '午餐' / '晚餐' / '點心'
  total_calories: number;
  total_protein_g: number;
  total_fat_g: number;
  total_carbs_g: number;
  items: DashboardItem[];
}

export interface DashboardData {
  date: string;
  profile_completed: boolean;
  nutrition_target: NutritionTargetSummary | null;
  consumed: Nutrients;
  remaining: Nutrients;          // 允許負值（已超標）
  progress_percent: ProgressPercent;
  is_over: IsOver;
  warnings: WarningItem[];
  today_meals: DashboardMeal[];
}

interface DashboardResponse {
  data: DashboardData;
}

/**
 * Dashboard 一次拿齊：個人資料完成度、每日營養目標、今日攝取、剩餘、進度、警示、今日餐點。
 * Vue component 不要直接呼叫 axios — 一律走這個 service。
 */
export const dashboardService = {
  fetchTodayDashboard(date?: string): Promise<DashboardData> {
    const params: Record<string, string> = {};
    if (date) params.date = date;
    return http
      .get<DashboardResponse>('/dashboard/today', { params })
      .then((r) => r.data.data);
  },
};

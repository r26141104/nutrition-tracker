import http from './http';

// ========================================================================
// 流程 18：熱量目標自動修正
// ========================================================================
export type CalorieAdjustmentType =
  | 'insufficient_data' | 'keep'
  | 'decrease_calories' | 'increase_calories'
  | 'increase_activity' | 'observe_more';

export interface CalorieAdjustment {
  period_days: number;
  has_enough_data: boolean;
  average_daily_calories: number | null;
  start_weight_kg: number | null;
  end_weight_kg: number | null;
  actual_weight_change_kg: number | null;
  expected_weight_change_kg: number | null;
  suggestion_type: CalorieAdjustmentType;
  suggested_calorie_adjustment: number;
  message: string;
  disclaimer: string;
}

// ========================================================================
// 流程 19：營養缺口分析
// ========================================================================
export interface NutritionTargetSet {
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
}

export interface NutritionGap {
  has_enough_data: boolean;
  target?: NutritionTargetSet;
  consumed?: NutritionTargetSet;
  gap?: NutritionTargetSet;
  main_deficit: 'protein' | null;
  main_excess: 'fat' | 'carbs' | 'calories' | null;
  messages: string[];
  message?: string; // 資料不足時用
}

// ========================================================================
// 流程 20：蛋白質分配分析
// ========================================================================
export interface ProteinDistribution {
  has_enough_data: boolean;
  total_protein_g: number;
  by_meal_type: {
    breakfast: number;
    lunch: number;
    dinner: number;
    snack: number;
  };
  messages: string[];
}

// ========================================================================
// 流程 21：體重波動解釋
// ========================================================================
export interface WeightFluctuation {
  has_enough_data: boolean;
  latest_weight_kg: number | null;
  previous_weight_kg: number | null;
  seven_day_average_kg: number | null;
  message: string;
  possible_reasons: string[];
}

// ========================================================================
// 流程 22：飲食品質分數
// ========================================================================
export type DietQualityLevel =
  | 'insufficient_data' | 'needs_attention'
  | 'fair' | 'good' | 'excellent';

export interface DietQualityScore {
  has_enough_data: boolean;
  score: number;
  level: DietQualityLevel;
  breakdown: {
    protein: number;
    calories: number;
    fat: number;
    carbs: number;
    meal_logging: number;
    snack_drink_ratio: number;
  };
  feedback: string[];
}

// ========================================================================
// 流程 23：每週修正建議
// ========================================================================
export interface WeeklyCorrectionSuggestion {
  has_enough_data: boolean;
  strengths: string[];
  issues: string[];
  action_items: string[];
  disclaimer: string;
}

// ========================================================================
// API
// ========================================================================
interface DataWrap<T> { data: T }

export const analysisService = {
  fetchCalorieAdjustment(): Promise<CalorieAdjustment> {
    return http.get<DataWrap<CalorieAdjustment>>('/analysis/calorie-adjustment').then(r => r.data.data);
  },
  fetchNutritionGap(): Promise<NutritionGap> {
    return http.get<DataWrap<NutritionGap>>('/analysis/nutrition-gap').then(r => r.data.data);
  },
  fetchProteinDistribution(): Promise<ProteinDistribution> {
    return http.get<DataWrap<ProteinDistribution>>('/analysis/protein-distribution').then(r => r.data.data);
  },
  fetchWeightFluctuation(): Promise<WeightFluctuation> {
    return http.get<DataWrap<WeightFluctuation>>('/analysis/weight-fluctuation').then(r => r.data.data);
  },
  fetchDietQualityScore(): Promise<DietQualityScore> {
    return http.get<DataWrap<DietQualityScore>>('/analysis/diet-quality-score').then(r => r.data.data);
  },
  fetchWeeklyCorrectionSuggestions(): Promise<WeeklyCorrectionSuggestion> {
    return http.get<DataWrap<WeeklyCorrectionSuggestion>>('/analysis/weekly-correction-suggestions').then(r => r.data.data);
  },
};

// ========================================================================
// UI 共用常數
// ========================================================================
export const DIET_LEVEL_LABEL: Record<DietQualityLevel, string> = {
  insufficient_data: '資料不足',
  needs_attention:   '需注意',
  fair:              '尚可',
  good:              '良好',
  excellent:         '優秀',
};

export const DIET_LEVEL_COLOR: Record<DietQualityLevel, string> = {
  insufficient_data: '#94a3b8',
  needs_attention:   '#dc2626',
  fair:              '#f59e0b',
  good:              '#10b981',
  excellent:         '#0ea5e9',
};

export const CALORIE_SUGGESTION_LABEL: Record<CalorieAdjustmentType, string> = {
  insufficient_data:  '資料不足',
  keep:               '維持目前熱量',
  decrease_calories:  '小幅降低熱量',
  increase_calories:  '小幅提高熱量',
  increase_activity:  '增加活動量',
  observe_more:       '繼續觀察',
};

export const NUTRITION_LABEL: Record<string, string> = {
  protein:  '蛋白質',
  fat:      '脂肪',
  carbs:    '碳水',
  calories: '熱量',
};

import http from './http';

export type RecommendationCategory =
  | 'high_protein'
  | 'low_calorie'
  | 'low_fat'
  | 'by_goal';

export interface RecommendedFood {
  id: number;
  name: string;
  brand: string | null;
  category: string;        // foods.category（rice_box / noodle / ...）
  serving_unit: string;
  serving_size: number;
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
  // 修正四：資料來源與可信度（後端會回，前端 optional 防舊資料）
  source_type?: 'system_estimate' | 'user_custom' | 'imported' | 'official';
  confidence_level?: 'high' | 'medium' | 'low';
}

export interface RecommendationGroup {
  category: RecommendationCategory;
  title: string;
  reason: string;
  foods: RecommendedFood[];
}

export interface RemainingNutrition {
  calories: number;   // 允許負數（已超標）
  protein_g: number;
  fat_g: number;
  carbs_g: number;
}

export interface FoodRecommendation {
  remaining: RemainingNutrition | null; // 沒個人資料時為 null
  recommendation_groups: RecommendationGroup[];
  notes: string[];
}

interface FoodRecommendationResponse {
  data: FoodRecommendation;
}

/**
 * 簡單餐點建議 API。
 * Vue component 不要直接呼叫 axios — 一律走這個 service。
 */
export const foodRecommendationService = {
  /** GET /api/food-recommendations */
  fetchFoodRecommendations(): Promise<FoodRecommendation> {
    return http
      .get<FoodRecommendationResponse>('/food-recommendations')
      .then((r) => r.data.data);
  },
};

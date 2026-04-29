import http from './http';
import type { Food } from './foodService';

export interface NutritionEstimate {
  name: string;
  calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
  serving_unit: string;
  serving_size: number;
  category: string;
  notes: string;
}

export interface NutritionEstimateAndCreate {
  food: Food;
  ai_notes: string;
}

interface DataWrap<T> { data: T }
interface MessageWrap<T> { data: T; message: string }

/**
 * AI 食物營養估算（Gemini API）。
 * 兩個方法：
 *   - estimate: 只估算、不存（給 FoodEdit 用，使用者可以調整再儲存）
 *   - estimateAndCreate: 估算 + 直接建立（給 MealEdit 用，搜尋找不到時一鍵建立）
 */
export const nutritionEstimateService = {
  /** POST /api/foods/ai-estimate — 只回估算值，不存 DB */
  estimate(name: string): Promise<NutritionEstimate> {
    return http
      .post<DataWrap<NutritionEstimate>>('/foods/ai-estimate', { name })
      .then((r) => r.data.data);
  },

  /** POST /api/foods/ai-estimate-and-create — 估算 + 寫入 foods（標記 ai_estimate / low） */
  estimateAndCreate(name: string): Promise<NutritionEstimateAndCreate & { message: string }> {
    return http
      .post<MessageWrap<NutritionEstimateAndCreate>>('/foods/ai-estimate-and-create', { name })
      .then((r) => ({ ...r.data.data, message: r.data.message }));
  },
};

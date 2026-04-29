import http from './http';

/**
 * 後端 NutritionTargetController::show 的回傳格式有兩種：
 *  - ready=true：已完成計算
 *  - ready=false：profile 未設或未填齊
 */

export interface NutritionTargetReady {
  ready: true;
  age: number;
  target_weight_kg: number;
  bmr: number;
  tdee: number;
  daily_calories: number;
  protein_g: number;
  fat_g: number;
  carbs_g: number;
  warnings: string[];
  note: string;
}

export interface NutritionTargetNotReady {
  ready: false;
  reason: 'profile_not_set' | 'profile_incomplete';
  message: string;
  warnings: string[];
}

export type NutritionTargetResponse = NutritionTargetReady | NutritionTargetNotReady;

export const nutritionTargetService = {
  /**
   * 取得目前登入者的每日營養目標。
   * 若 profile 未設或不完整，會回傳 ready=false 與提示訊息。
   */
  getTarget(): Promise<NutritionTargetResponse> {
    return http
      .get<NutritionTargetResponse>('/nutrition-target')
      .then((r) => r.data);
  },
};

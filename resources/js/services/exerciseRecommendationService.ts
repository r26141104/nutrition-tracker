import http from './http';

export type GoalType = 'lose_fat' | 'gain_muscle' | 'maintain';

export interface WeeklyPlanItem {
  day: string;        // 例如「週一」
  suggestion: string; // 例如「快走或慢跑 30 分鐘」
}

export interface ExerciseRecommendation {
  goal_type: GoalType | null;            // 沒個人資料時為 null
  main_focus: string;
  cardio: string[];
  resistance_training: string[];
  weekly_plan: WeeklyPlanItem[];
  notes: string[];
}

interface ExerciseRecommendationResponse {
  data: ExerciseRecommendation;
}

/**
 * 簡單運動建議 API。
 * Vue component 不要直接呼叫 axios — 一律走這個 service。
 */
export const exerciseRecommendationService = {
  /** GET /api/exercise-recommendations */
  fetchExerciseRecommendations(): Promise<ExerciseRecommendation> {
    return http
      .get<ExerciseRecommendationResponse>('/exercise-recommendations')
      .then((r) => r.data.data);
  },
};

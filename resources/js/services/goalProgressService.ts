import http from './http';

export type GoalStatus =
  | 'no_profile'
  | 'no_weight_record'
  | 'in_progress'
  | 'near_goal'
  | 'reached_goal'
  | 'maintain';

export type GoalType = 'lose_fat' | 'gain_muscle' | 'maintain';

export interface GoalProgress {
  goal_type: GoalType | null;
  height_cm: number | null;
  current_weight_kg: number | null;
  target_bmi: number | null;
  target_weight_kg: number | null;
  weight_difference_kg: number | null;
  estimated_weekly_change_kg: number | null;
  estimated_weeks: number | null;
  estimated_target_date: string | null; // 'YYYY-MM-DD'
  status: GoalStatus;
  message: string;
  disclaimer: string | null;
}

interface GoalProgressResponse {
  data: GoalProgress;
}

/**
 * 目標進度與達標時間估算 API。
 * Vue component 不要直接呼叫 axios — 一律走這個 service。
 */
export const goalProgressService = {
  /** GET /api/goal-progress */
  fetchGoalProgress(): Promise<GoalProgress> {
    return http
      .get<GoalProgressResponse>('/goal-progress')
      .then((r) => r.data.data);
  },
};

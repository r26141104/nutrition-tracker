import http from './http';

export interface Achievement {
  type: 'meal' | 'body' | 'water';
  level: number;
  label: string;
  achieved: boolean;
}

export interface StreakInfo {
  meal_streak: number;
  body_record_streak: number;
  water_streak: number;
  longest_meal_streak: number;
  achievements: Achievement[];
  total_meal_days: number;
  total_body_records: number;
}

interface DataWrap<T> { data: T }

export const streakService = {
  /** GET /api/streak */
  fetchStreak(): Promise<StreakInfo> {
    return http.get<DataWrap<StreakInfo>>('/streak').then(r => r.data.data);
  },
};

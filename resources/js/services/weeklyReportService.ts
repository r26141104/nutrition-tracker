import http from './http';

export interface MostFrequentFood {
  food_id: number;
  food_name: string;
  count: number;
}

export interface WeeklyReport {
  week_start: string;          // 'YYYY-MM-DD' (週一)
  week_end: string;            // 'YYYY-MM-DD' (週日)
  logged_meal_days: number;
  average_calories: number;
  average_protein_g: number;
  average_fat_g: number;
  average_carbs_g: number;
  weight_change_kg: number | null;
  over_target_days: number;
  most_frequent_foods: MostFrequentFood[];
  summary: string[];
  warnings: string[];
}

interface WeeklyReportResponse {
  data: WeeklyReport;
}

/**
 * 每週飲食 + 體重報告 API。
 * Vue component 不要直接呼叫 axios — 一律走這個 service。
 */
export const weeklyReportService = {
  /** GET /api/weekly-report/current */
  fetchCurrentWeeklyReport(): Promise<WeeklyReport> {
    return http
      .get<WeeklyReportResponse>('/weekly-report/current')
      .then((r) => r.data.data);
  },
};

import http from './http';

export interface BodyRecord {
  id: number;
  record_date: string;       // 'YYYY-MM-DD'
  weight_kg: number;
  bmi: number;               // 後端自動算
  note: string | null;
  // 階段 G：身體量測補完
  waist_cm: number | null;
  hip_cm: number | null;
  chest_cm: number | null;
  arm_cm: number | null;
  thigh_cm: number | null;
  body_fat_percent: number | null;
  muscle_mass_kg: number | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface BodyRecordPayload {
  record_date: string;       // 'YYYY-MM-DD'
  weight_kg: number;
  note?: string | null;
  // 階段 G：optional 身體量測欄位
  waist_cm?: number | null;
  hip_cm?: number | null;
  chest_cm?: number | null;
  arm_cm?: number | null;
  thigh_cm?: number | null;
  body_fat_percent?: number | null;
  muscle_mass_kg?: number | null;
  // 注意：故意不收 bmi 和 user_id，後端自動處理
}

interface BodyRecordListResponse {
  data: BodyRecord[];
}

interface BodyRecordResponse {
  body_record: BodyRecord;
}

// === 趨勢圖型別 ===

export type TrendDays = 7 | 30 | 90;

export interface BodyRecordTrendPoint {
  record_date: string; // 'YYYY-MM-DD'
  weight_kg: number;
  bmi: number;
}

export interface BodyRecordTrendInsights {
  has_sufficient_data: boolean;
  seven_day_average_kg: number | null;
  thirty_day_change_kg: number | null;
  today_vs_seven_day: {
    today_weight_kg: number;
    seven_day_avg_kg: number;
    difference_kg: number;
  } | null;
  message: string;
}

export interface BodyRecordTrend {
  days: number;
  target_weight_kg: number | null;
  latest_weight_kg: number | null;
  latest_bmi: number | null;
  records: BodyRecordTrendPoint[];
  // 修正六：體重趨勢洞察（7 日平均 / 30 日變化 / 今日 vs 7 日比較 / 資料是否足夠）
  insights: BodyRecordTrendInsights;
}

interface BodyRecordTrendResponse {
  data: BodyRecordTrend;
}

/**
 * 體重紀錄 API。
 * Vue component 不要直接呼叫 axios — 一律走這個 service。
 */
export const bodyRecordService = {
  /** GET /api/body-records — 列表，由新到舊 */
  fetchBodyRecords(): Promise<BodyRecord[]> {
    return http
      .get<BodyRecordListResponse>('/body-records')
      .then((r) => r.data.data);
  },

  /** GET /api/body-records/{id} */
  fetchBodyRecord(id: number): Promise<BodyRecord> {
    return http
      .get<BodyRecordResponse>(`/body-records/${id}`)
      .then((r) => r.data.body_record);
  },

  /**
   * POST /api/body-records — 新增
   * 注意：同一天已有紀錄 → 後端自動更新那筆（updateOrCreate）
   */
  createBodyRecord(payload: BodyRecordPayload): Promise<BodyRecord> {
    return http
      .post<BodyRecordResponse>('/body-records', payload)
      .then((r) => r.data.body_record);
  },

  /** PUT /api/body-records/{id} — 修改 */
  updateBodyRecord(id: number, payload: BodyRecordPayload): Promise<BodyRecord> {
    return http
      .put<BodyRecordResponse>(`/body-records/${id}`, payload)
      .then((r) => r.data.body_record);
  },

  /** DELETE /api/body-records/{id} */
  deleteBodyRecord(id: number): Promise<void> {
    return http.delete(`/body-records/${id}`).then(() => undefined);
  },

  /**
   * GET /api/body-records/trend?days={7|30|90}
   * 回傳最近 N 天的體重紀錄（升冪），用於折線圖。
   */
  fetchBodyRecordTrend(days: TrendDays = 30): Promise<BodyRecordTrend> {
    return http
      .get<BodyRecordTrendResponse>('/body-records/trend', { params: { days } })
      .then((r) => r.data.data);
  },
};
